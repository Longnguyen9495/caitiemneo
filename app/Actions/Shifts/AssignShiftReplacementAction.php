<?php

namespace App\Actions\Shifts;

use App\Actions\Attendance\ScheduleShiftAction;
use App\Enums\AuditAction;
use App\Enums\ShiftRequestStatus;
use App\Enums\ShiftRequestType;
use App\Models\ShiftAssignment;
use App\Models\ShiftReplacement;
use App\Models\ShiftRequest;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Payroll\PayrollLockGuard;
use App\Services\Shifts\ShiftRequestNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignShiftReplacementAction
{
    public function __construct(
        private ScheduleShiftAction $schedule,
        private PayrollLockGuard $payrollLock,
        private AuditRecorder $audit,
        private ShiftRequestNotifier $notifier,
    ) {}

    /** @return Collection<int, User> */
    public function candidates(User $actor, ShiftRequest $request): Collection
    {
        $this->assertManagerScope($actor, $request);
        $assignment = $request->shiftAssignment;

        return User::query()
            ->active()
            ->staff()
            ->whereKeyNot($request->requester_id)
            ->whereHas('branchAssignments', fn ($query) => $query
                ->where('branch_id', $request->branch_id)
                ->covering($request->work_date))
            ->whereDoesntHave('attendanceRecords', fn ($query) => $query
                ->where('work_date', $request->work_date))
            ->whereDoesntHave('submittedShiftRequests', fn ($query) => $query
                ->whereIn('status', [ShiftRequestStatus::PendingApproval->value, ShiftRequestStatus::PendingRecipient->value, ShiftRequestStatus::RecipientConfirmed->value])
                ->whereDate('work_date', $request->work_date))
            ->orderBy('name')
            ->get()
            ->filter(fn (User $employee): bool => ! ShiftAssignment::query()
                ->where('employee_id', $employee->getKey())
                ->where('planned_start_at', '<', $assignment->planned_end_at)
                ->where('planned_end_at', '>', $assignment->planned_start_at)
                ->exists())
            ->values();
    }

    public function handle(User $actor, ShiftRequest $request, User $replacement): ShiftReplacement
    {
        return DB::transaction(function () use ($actor, $request, $replacement): ShiftReplacement {
            $request = ShiftRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $this->assertManagerScope($actor, $request);

            if ($request->type !== ShiftRequestType::Leave || $request->status !== ShiftRequestStatus::Approved) {
                throw ValidationException::withMessages(['request' => 'Chỉ đơn nghỉ đã duyệt mới được phân người thay.']);
            }

            if (ShiftReplacement::query()->where('original_shift_assignment_id', $request->shift_assignment_id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['request' => 'Ca này đã có người thay.']);
            }

            $original = ShiftAssignment::query()->lockForUpdate()->findOrFail($request->shift_assignment_id);
            $this->payrollLock->assertUnlocked($original->employee_id, $original->work_date, 'shift_assignment_id');

            if (! $replacement->is_active || ! $replacement->canAccessBranch($request->branch_id, $request->work_date)) {
                throw ValidationException::withMessages(['replacement_employee_id' => 'Nhân viên thay không thuộc chi nhánh vào ngày làm.']);
            }

            $hasConflict = ShiftAssignment::query()
                ->where('employee_id', $replacement->getKey())
                ->where('planned_start_at', '<', $original->planned_end_at)
                ->where('planned_end_at', '>', $original->planned_start_at)
                ->exists();

            if ($hasConflict) {
                throw ValidationException::withMessages(['replacement_employee_id' => 'Nhân viên thay đã có ca trùng giờ.']);
            }

            $replacementAssignment = $this->schedule->handle(
                $original->branch,
                $original->workShift,
                $replacement,
                $original->work_date->toDateString(),
                $actor,
                sprintf('Thay ca cho %s theo đơn #%d.', $original->employee->name, $request->getKey()),
            );

            $record = ShiftReplacement::query()->create([
                'shift_request_id' => $request->getKey(),
                'original_shift_assignment_id' => $original->getKey(),
                'replacement_employee_id' => $replacement->getKey(),
                'replacement_shift_assignment_id' => $replacementAssignment->getKey(),
                'assigned_by' => $actor->getKey(),
                'assigned_at' => now(),
            ]);

            $this->audit->record($record, $actor, AuditAction::Created, null, $record->getAttributes(), 'Phân nhân viên thay ca.', $request->branch_id);
            $this->notifier->afterCommit($request, [$original->employee, $replacement], sprintf('%s được phân thay ca ngày %s theo đơn nghỉ đã duyệt.', $replacement->name, $request->work_date->format('d/m/Y')));

            return $record;
        });
    }

    private function assertManagerScope(User $actor, ShiftRequest $request): void
    {
        if ((! $actor->isOwner() && ! $actor->isManager()) || ! $actor->canAccessBranch($request->branch_id, $request->work_date)) {
            throw ValidationException::withMessages(['request' => 'Bạn không có quyền phân người thay tại chi nhánh này.']);
        }
    }
}
