<?php

namespace App\Actions\Shifts;

use App\Actions\Shifts\Concerns\AssertsBranchLeadership;
use App\Enums\AuditAction;
use App\Enums\ShiftRequestStatus;
use App\Enums\ShiftRequestType;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequest;
use App\Models\ShiftRequestHistory;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Payroll\PayrollLockGuard;
use App\Services\Shifts\PaidLeaveAllowance;
use App\Services\Shifts\ShiftRequestNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The life of a leave or swap request, from asking to decided.
 *
 * Every method here reloads the request under a row lock before looking at its
 * status. Two managers hitting Approve on the same request within the same
 * second is not a rare event in a shop, and the model handed in by the router
 * was read before either of them clicked.
 */
class ManageShiftRequestAction
{
    use AssertsBranchLeadership;

    public function __construct(
        private AuditRecorder $audit,
        private PayrollLockGuard $payrollLock,
        private ShiftRequestNotifier $notifier,
        private PaidLeaveAllowance $paidLeave,
    ) {}

    public function createLeave(User $requester, int $assignmentId, ?string $reason = null): ShiftRequest
    {
        return DB::transaction(function () use ($requester, $assignmentId, $reason): ShiftRequest {
            $assignment = ShiftAssignment::query()->lockForUpdate()->findOrFail($assignmentId);
            $this->assertRequesterOwnsAssignment($requester, $assignment);
            $this->assertMutableAssignment($assignment);
            $this->assertNoOpenRequest($assignment->getKey());

            $request = ShiftRequest::query()->create([
                'type' => ShiftRequestType::Leave,
                'status' => ShiftRequestStatus::PendingApproval,
                'branch_id' => $assignment->branch_id,
                'requester_id' => $requester->getKey(),
                'shift_assignment_id' => $assignment->getKey(),
                'work_date' => $assignment->work_date,
                'reason' => $reason,
            ]);

            $this->recordTransition($request, null, ShiftRequestStatus::PendingApproval, $requester, $reason);
            $this->audit->record($request, $requester, AuditAction::Created, null, $request->getAttributes(), $reason, $request->branch_id);
            $this->notify($request, $this->notifier->managersFor($request), sprintf('%s đã gửi đơn nghỉ ca ngày %s cần duyệt.', $requester->name, $this->dateLabel($request)));

            return $request;
        });
    }

    public function createSwap(User $requester, int $assignmentId, int $recipientId, int $counterAssignmentId, ?string $reason = null): ShiftRequest
    {
        return DB::transaction(function () use ($requester, $assignmentId, $recipientId, $counterAssignmentId, $reason): ShiftRequest {
            $assignment = ShiftAssignment::query()->lockForUpdate()->findOrFail($assignmentId);
            $counter = ShiftAssignment::query()->lockForUpdate()->findOrFail($counterAssignmentId);
            $recipient = User::query()->active()->findOrFail($recipientId);

            $this->assertRequesterOwnsAssignment($requester, $assignment);
            $this->assertMutableAssignment($assignment);
            $this->assertMutableAssignment($counter);

            if ($recipient->getKey() === $requester->getKey() || $counter->employee_id !== $recipient->getKey()) {
                throw ValidationException::withMessages(['recipient_id' => 'Người nhận phải sở hữu ca đối ứng.']);
            }

            if (! $this->isExchangeable($assignment, $counter) || ! $recipient->canAccessBranch($assignment->branch_id, $assignment->work_date)) {
                throw ValidationException::withMessages(['counter_shift_assignment_id' => 'Ca đổi phải cùng ngày, cùng chi nhánh và người nhận còn được phân công hợp lệ.']);
            }

            $this->assertSwapLeavesNoOverlap($requester, $recipient, $assignment, $counter);

            $this->assertNoOpenRequest($assignment->getKey());
            $this->assertNoOpenRequest($counter->getKey());

            $request = ShiftRequest::query()->create([
                'type' => ShiftRequestType::Swap,
                'status' => ShiftRequestStatus::PendingRecipient,
                'branch_id' => $assignment->branch_id,
                'requester_id' => $requester->getKey(),
                'recipient_id' => $recipient->getKey(),
                'shift_assignment_id' => $assignment->getKey(),
                'counter_shift_assignment_id' => $counter->getKey(),
                'work_date' => $assignment->work_date,
                'reason' => $reason,
            ]);

            $this->recordTransition($request, null, ShiftRequestStatus::PendingRecipient, $requester, $reason);
            $this->audit->record($request, $requester, AuditAction::Created, null, $request->getAttributes(), $reason, $request->branch_id);
            $this->notify($request, [$recipient], sprintf('%s đề nghị đổi ca ngày %s với bạn.', $requester->name, $this->dateLabel($request)));

            return $request;
        });
    }

    public function cancel(User $actor, ShiftRequest $request, ?string $note = null): ShiftRequest
    {
        return DB::transaction(function () use ($actor, $request, $note): ShiftRequest {
            $request = $this->lockRequest($request);

            if ($request->requester_id !== $actor->getKey() || ! $request->status->canBeCancelledByRequester()) {
                throw ValidationException::withMessages(['request' => 'Đơn này không còn có thể hủy.']);
            }

            $request = $this->transition($request, ShiftRequestStatus::Cancelled, $actor, $note, AuditAction::Cancelled);

            $this->notify(
                $request,
                $this->notifier->managersFor($request)->merge($this->recipientOf($request)),
                sprintf('%s đã hủy đơn ca làm ngày %s.', $actor->name, $this->dateLabel($request)),
            );

            return $request;
        });
    }

    public function respond(User $actor, ShiftRequest $request, bool $accepted, ?string $note = null): ShiftRequest
    {
        return DB::transaction(function () use ($actor, $request, $accepted, $note): ShiftRequest {
            $request = $this->lockRequest($request);

            if ($request->type !== ShiftRequestType::Swap || $request->recipient_id !== $actor->getKey() || $request->status !== ShiftRequestStatus::PendingRecipient) {
                throw ValidationException::withMessages(['request' => 'Bạn không thể phản hồi đơn đổi ca này.']);
            }

            $request = $this->transition(
                $request,
                $accepted ? ShiftRequestStatus::RecipientConfirmed : ShiftRequestStatus::Rejected,
                $actor,
                $note,
                $accepted ? AuditAction::Updated : AuditAction::Rejected,
            );

            $audience = $this->requesterOf($request);

            if ($accepted) {
                $audience = $audience->merge($this->notifier->managersFor($request));
            }

            $this->notify($request, $audience, sprintf('%s đã %s đề nghị đổi ca ngày %s.', $actor->name, $accepted ? 'đồng ý' : 'từ chối', $this->dateLabel($request)));

            return $request;
        });
    }

    public function decide(User $actor, ShiftRequest $request, bool $approved, ?string $note = null): ShiftRequest
    {
        return DB::transaction(function () use ($actor, $request, $approved, $note): ShiftRequest {
            $request = $this->lockRequest($request);
            $this->assertManagerScope($actor, $request);
            $this->assertAwaitingDecision($request);

            $assignment = ShiftAssignment::query()->lockForUpdate()->findOrFail($request->shift_assignment_id);
            $this->assertMutableAssignment($assignment);

            // Ca đối ứng vẫn được khóa và soát lại ngay cả khi sắp từ chối: nếu
            // nó đã đổi từ lúc gửi đơn thì người duyệt cần biết, chứ không phải
            // nhận một câu "đã từ chối" cho thứ không còn tồn tại.
            $counter = $request->type === ShiftRequestType::Swap
                ? $this->lockExchangeableCounter($request, $assignment)
                : null;

            if (! $approved) {
                $request = $this->transition($request, ShiftRequestStatus::Rejected, $actor, $note, AuditAction::Rejected);
                $this->notify($request, $this->partiesTo($request), sprintf('Đơn %s ngày %s đã bị từ chối.', $request->type->label(), $this->dateLabel($request)));

                return $request;
            }

            if ($counter === null) {
                $requester = User::query()->lockForUpdate()->findOrFail($request->requester_id);
                $request->leave_entitlement = $this->paidLeave->claim($requester, $request->branch_id, $request->work_date, $actor);
            } else {
                $this->swapEmployees($assignment, $counter);
            }

            $request = $this->transition($request, ShiftRequestStatus::Approved, $actor, $note, AuditAction::Approved);
            $this->notify($request, $this->partiesTo($request), sprintf('Đơn %s ngày %s đã được duyệt.', $request->type->label(), $this->dateLabel($request)));

            return $request;
        });
    }

    private function lockRequest(ShiftRequest $request): ShiftRequest
    {
        return ShiftRequest::query()->lockForUpdate()->findOrFail($request->getKey());
    }

    /** Only a request actually waiting on a manager may be decided. */
    private function assertAwaitingDecision(ShiftRequest $request): void
    {
        $awaiting = $request->type === ShiftRequestType::Leave
            ? ShiftRequestStatus::PendingApproval
            : ShiftRequestStatus::RecipientConfirmed;

        if ($request->status !== $awaiting) {
            throw ValidationException::withMessages(['request' => 'Trạng thái đơn không hợp lệ để duyệt.']);
        }
    }

    /**
     * The counter shift of a swap, locked and still fit to exchange.
     *
     * A roster can move between the request being raised and being approved, so
     * the pairing is proved again here rather than trusted from the request.
     */
    private function lockExchangeableCounter(ShiftRequest $request, ShiftAssignment $assignment): ShiftAssignment
    {
        $counter = ShiftAssignment::query()->lockForUpdate()->findOrFail($request->counter_shift_assignment_id);
        $this->assertMutableAssignment($counter);

        if ($counter->employee_id !== $request->recipient_id || ! $this->isExchangeable($assignment, $counter)) {
            throw ValidationException::withMessages(['request' => 'Ca đối ứng đã thay đổi hoặc không còn hợp lệ.']);
        }

        $this->assertSwapLeavesNoOverlap(
            User::query()->findOrFail($request->requester_id),
            User::query()->findOrFail($request->recipient_id),
            $assignment,
            $counter,
        );

        return $counter;
    }

    /** Two shifts may only be traded within the same shop on the same day. */
    private function isExchangeable(ShiftAssignment $assignment, ShiftAssignment $counter): bool
    {
        return $assignment->branch_id === $counter->branch_id
            && $assignment->work_date->toDateString() === $counter->work_date->toDateString();
    }

    /** Neither side may end up double-booked once the two shifts change hands. */
    private function assertSwapLeavesNoOverlap(User $requester, User $recipient, ShiftAssignment $assignment, ShiftAssignment $counter): void
    {
        $traded = [$assignment->getKey(), $counter->getKey()];

        $this->assertNoConflictingAssignment($requester, $counter, $traded);
        $this->assertNoConflictingAssignment($recipient, $assignment, $traded);
    }

    private function transition(ShiftRequest $request, ShiftRequestStatus $to, User $actor, ?string $note, AuditAction $auditAction): ShiftRequest
    {
        $from = $request->status;
        $before = $request->getAttributes();
        $request->forceFill(['status' => $to, 'processed_by' => $actor->getKey(), 'processed_at' => now()])->save();
        $this->recordTransition($request, $from, $to, $actor, $note);
        $this->audit->record($request, $actor, $auditAction, $before, $request->fresh()->getAttributes(), $note, $request->branch_id);

        return $request->refresh();
    }

    private function recordTransition(ShiftRequest $request, ?ShiftRequestStatus $from, ShiftRequestStatus $to, User $actor, ?string $note): void
    {
        ShiftRequestHistory::query()->create([
            'shift_request_id' => $request->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor->getKey(),
            'actor_name' => $actor->name,
            'note' => $note,
        ]);
    }

    /** @param  Collection<int, User>|array<int, User>  $audience */
    private function notify(ShiftRequest $request, Collection|array $audience, string $message): void
    {
        $this->notifier->afterCommit($request, $audience, $message);
    }

    /**
     * Both sides of the request: whoever asked, and whoever was asked.
     *
     * @return Collection<int, User>
     */
    private function partiesTo(ShiftRequest $request): Collection
    {
        return $this->requesterOf($request)->merge($this->recipientOf($request));
    }

    /** @return Collection<int, User> */
    private function requesterOf(ShiftRequest $request): Collection
    {
        return collect([User::query()->find($request->requester_id)])->filter()->values();
    }

    /** @return Collection<int, User> */
    private function recipientOf(ShiftRequest $request): Collection
    {
        if ($request->recipient_id === null) {
            return collect();
        }

        return collect([User::query()->find($request->recipient_id)])->filter()->values();
    }

    private function dateLabel(ShiftRequest $request): string
    {
        return $request->work_date->format('d/m/Y');
    }

    private function assertRequesterOwnsAssignment(User $requester, ShiftAssignment $assignment): void
    {
        if (! $requester->is_active || $assignment->employee_id !== $requester->getKey() || ! $requester->canAccessBranch($assignment->branch_id, $assignment->work_date)) {
            throw ValidationException::withMessages(['shift_assignment_id' => 'Ca làm không thuộc phạm vi hợp lệ của bạn.']);
        }
    }

    private function assertMutableAssignment(ShiftAssignment $assignment): void
    {
        if ($assignment->attendanceRecord()->exists()) {
            throw ValidationException::withMessages(['shift_assignment_id' => 'Ca đã có chấm công nên không thể thay đổi.']);
        }

        $this->payrollLock->assertUnlocked($assignment->employee_id, $assignment->work_date, 'shift_assignment_id');
    }

    private function assertNoOpenRequest(int $assignmentId): void
    {
        if (ShiftRequest::query()->open()->where(fn ($query) => $query->where('shift_assignment_id', $assignmentId)->orWhere('counter_shift_assignment_id', $assignmentId))->lockForUpdate()->exists()) {
            throw ValidationException::withMessages(['shift_assignment_id' => 'Ca này đã có một đơn đang chờ xử lý.']);
        }
    }

    /** @param  array<int, int>  $excludedAssignmentIds */
    private function assertNoConflictingAssignment(User $employee, ShiftAssignment $incoming, array $excludedAssignmentIds): void
    {
        $conflicts = ShiftAssignment::query()
            ->forEmployee($employee)
            ->whereNotIn('id', $excludedAssignmentIds)
            ->overlappingAssignment($incoming)
            ->exists();

        if ($conflicts) {
            throw ValidationException::withMessages(['request' => 'Đổi ca tạo ra lịch làm việc chồng lấn.']);
        }
    }

    private function swapEmployees(ShiftAssignment $assignment, ShiftAssignment $counter): void
    {
        $requesterId = $assignment->employee_id;
        $recipientId = $counter->employee_id;

        $assignment->forceFill(['employee_id' => $recipientId])->save();
        $counter->forceFill(['employee_id' => $requesterId])->save();
    }

    private function assertManagerScope(User $actor, ShiftRequest $request): void
    {
        $this->assertLeadsBranchOn(
            $actor,
            $request->branch_id,
            $request->work_date,
            'request',
            'Bạn không có quyền duyệt đơn tại chi nhánh này.',
        );
    }
}
