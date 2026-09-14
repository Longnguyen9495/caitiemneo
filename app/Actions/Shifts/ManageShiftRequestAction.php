<?php

namespace App\Actions\Shifts;

use App\Enums\AuditAction;
use App\Enums\LeaveEntitlement;
use App\Enums\ShiftRequestStatus;
use App\Enums\ShiftRequestType;
use App\Models\MonthlyPaidLeaveDay;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequest;
use App\Models\ShiftRequestHistory;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Payroll\PayrollLockGuard;
use App\Services\Shifts\ShiftRequestNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageShiftRequestAction
{
    public function __construct(
        private AuditRecorder $audit,
        private PayrollLockGuard $payrollLock,
        private ShiftRequestNotifier $notifier,
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
            $this->notifier->afterCommit($request, $this->notifier->managersFor($request), sprintf('%s đã gửi đơn nghỉ ca ngày %s cần duyệt.', $requester->name, $request->work_date->format('d/m/Y')));

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

            if ($assignment->branch_id !== $counter->branch_id
                || $assignment->work_date->toDateString() !== $counter->work_date->toDateString()
                || ! $recipient->canAccessBranch($assignment->branch_id, $assignment->work_date)) {
                throw ValidationException::withMessages(['counter_shift_assignment_id' => 'Ca đổi phải cùng ngày, cùng chi nhánh và người nhận còn được phân công hợp lệ.']);
            }

            $this->assertNoConflictingAssignment($requester, $counter, [$assignment->getKey(), $counter->getKey()]);
            $this->assertNoConflictingAssignment($recipient, $assignment, [$assignment->getKey(), $counter->getKey()]);

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
            $this->notifier->afterCommit($request, [$recipient], sprintf('%s đề nghị đổi ca ngày %s với bạn.', $requester->name, $request->work_date->format('d/m/Y')));

            return $request;
        });
    }

    public function cancel(User $actor, ShiftRequest $request, ?string $note = null): ShiftRequest
    {
        return DB::transaction(function () use ($actor, $request, $note): ShiftRequest {
            $request = ShiftRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if ($request->requester_id !== $actor->getKey() || ! $request->status->canBeCancelledByRequester()) {
                throw ValidationException::withMessages(['request' => 'Đơn này không còn có thể hủy.']);
            }

            $request = $this->transition($request, ShiftRequestStatus::Cancelled, $actor, $note, AuditAction::Cancelled);
            $recipients = $this->notifier->managersFor($request);

            if ($request->recipient_id !== null) {
                $recipients->push(User::query()->find($request->recipient_id))->filter();
            }

            $this->notifier->afterCommit($request, $recipients, sprintf('%s đã hủy đơn ca làm ngày %s.', $actor->name, $request->work_date->format('d/m/Y')));

            return $request;
        });
    }

    public function respond(User $actor, ShiftRequest $request, bool $accepted, ?string $note = null): ShiftRequest
    {
        return DB::transaction(function () use ($actor, $request, $accepted, $note): ShiftRequest {
            $request = ShiftRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if ($request->type !== ShiftRequestType::Swap || $request->recipient_id !== $actor->getKey() || $request->status !== ShiftRequestStatus::PendingRecipient) {
                throw ValidationException::withMessages(['request' => 'Bạn không thể phản hồi đơn đổi ca này.']);
            }

            $request = $this->transition($request, $accepted ? ShiftRequestStatus::RecipientConfirmed : ShiftRequestStatus::Rejected, $actor, $note, $accepted ? AuditAction::Updated : AuditAction::Rejected);
            $recipients = collect([User::query()->find($request->requester_id)]);

            if ($accepted) {
                $recipients = $recipients->merge($this->notifier->managersFor($request));
            }

            $this->notifier->afterCommit($request, $recipients, sprintf('%s đã %s đề nghị đổi ca ngày %s.', $actor->name, $accepted ? 'đồng ý' : 'từ chối', $request->work_date->format('d/m/Y')));

            return $request;
        });
    }

    public function decide(User $actor, ShiftRequest $request, bool $approved, ?string $note = null): ShiftRequest
    {
        return DB::transaction(function () use ($actor, $request, $approved, $note): ShiftRequest {
            $request = ShiftRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $this->assertManagerScope($actor, $request);

            $allowed = $request->type === ShiftRequestType::Leave
                ? $request->status === ShiftRequestStatus::PendingApproval
                : $request->status === ShiftRequestStatus::RecipientConfirmed;

            if (! $allowed) {
                throw ValidationException::withMessages(['request' => 'Trạng thái đơn không hợp lệ để duyệt.']);
            }

            $assignment = ShiftAssignment::query()->lockForUpdate()->findOrFail($request->shift_assignment_id);
            $this->assertMutableAssignment($assignment);

            if ($request->type === ShiftRequestType::Swap) {
                $counter = ShiftAssignment::query()->lockForUpdate()->findOrFail($request->counter_shift_assignment_id);
                $this->assertMutableAssignment($counter);

                if ($counter->employee_id !== $request->recipient_id
                    || $counter->branch_id !== $assignment->branch_id
                    || $counter->work_date->toDateString() !== $assignment->work_date->toDateString()) {
                    throw ValidationException::withMessages(['request' => 'Ca đối ứng đã thay đổi hoặc không còn hợp lệ.']);
                }

                $requester = User::query()->findOrFail($request->requester_id);
                $recipient = User::query()->findOrFail($request->recipient_id);
                $this->assertNoConflictingAssignment($requester, $counter, [$assignment->getKey(), $counter->getKey()]);
                $this->assertNoConflictingAssignment($recipient, $assignment, [$assignment->getKey(), $counter->getKey()]);
            }

            if (! $approved) {
                $request = $this->transition($request, ShiftRequestStatus::Rejected, $actor, $note, AuditAction::Rejected);
                $recipients = collect([User::query()->find($request->requester_id)]);

                if ($request->recipient_id !== null) {
                    $recipients->push(User::query()->find($request->recipient_id));
                }

                $this->notifier->afterCommit($request, $recipients, sprintf('Đơn %s ngày %s đã bị từ chối.', $request->type->label(), $request->work_date->format('d/m/Y')));

                return $request;
            }

            if ($request->type === ShiftRequestType::Leave) {
                User::query()->lockForUpdate()->findOrFail($request->requester_id);

                $paidLeaveDaysThisMonth = MonthlyPaidLeaveDay::query()
                    ->where('employee_id', $request->requester_id)
                    ->whereBetween('leave_date', [
                        $request->work_date->copy()->startOfMonth()->toDateString(),
                        $request->work_date->copy()->endOfMonth()->toDateString(),
                    ])
                    ->lockForUpdate()
                    ->count();

                $request->leave_entitlement = $paidLeaveDaysThisMonth < 2
                    ? LeaveEntitlement::Paid
                    : LeaveEntitlement::Unpaid;

                if ($request->leave_entitlement === LeaveEntitlement::Paid) {
                    MonthlyPaidLeaveDay::query()->create([
                        'employee_id' => $request->requester_id,
                        'branch_id' => $request->branch_id,
                        'leave_date' => $request->work_date,
                        'scheduled_by' => $actor->getKey(),
                    ]);
                }
            } else {
                $this->swapEmployees($assignment, $counter);
            }

            $request = $this->transition($request, ShiftRequestStatus::Approved, $actor, $note, AuditAction::Approved);
            $recipients = collect([User::query()->find($request->requester_id)]);

            if ($request->recipient_id !== null) {
                $recipients->push(User::query()->find($request->recipient_id));
            }

            $this->notifier->afterCommit($request, $recipients, sprintf('Đơn %s ngày %s đã được duyệt.', $request->type->label(), $request->work_date->format('d/m/Y')));

            return $request;
        });
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

    /** @param array<int, int> $excludedAssignmentIds */
    private function assertNoConflictingAssignment(User $employee, ShiftAssignment $incoming, array $excludedAssignmentIds): void
    {
        $conflicts = ShiftAssignment::query()
            ->where('employee_id', $employee->getKey())
            ->whereNotIn('id', $excludedAssignmentIds)
            ->where('planned_start_at', '<', $incoming->planned_end_at)
            ->where('planned_end_at', '>', $incoming->planned_start_at)
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
        if ((! $actor->isOwner() && ! $actor->isManager()) || ! $actor->canAccessBranch($request->branch_id, $request->work_date)) {
            throw ValidationException::withMessages(['request' => 'Bạn không có quyền duyệt đơn tại chi nhánh này.']);
        }
    }
}
