<?php

namespace App\Actions\Attendance;

use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Payroll\PayrollLockGuard;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Roster one employee onto one shift on one date.
 *
 * The planned times are snapshotted here rather than derived on read, which is
 * what makes a later edit of the shift template harmless: whether somebody was
 * late last Tuesday is decided against the times that were in force then.
 */
class ScheduleShiftAction
{
    public function __construct(private PayrollLockGuard $payrollLock) {}

    /** @throws ValidationException */
    public function handle(
        Branch $branch,
        WorkShift $shift,
        User $employee,
        string $workDate,
        ?User $actor = null,
        ?string $note = null,
    ): ShiftAssignment {
        $this->assertTemplateUsable($branch, $shift);
        $this->assertEmployeeStillWorks($employee);
        $this->assertEmployeePostedToBranch($branch, $employee, $workDate);
        $this->payrollLock->assertUnlocked($employee->getKey(), $workDate, 'work_date');

        $plannedStart = $shift->plannedStartOn($workDate);
        $plannedEnd = $shift->plannedEndOn($workDate);

        return DB::transaction(function () use ($branch, $shift, $employee, $workDate, $actor, $note, $plannedStart, $plannedEnd): ShiftAssignment {
            $this->assertNoOverlap($employee, $plannedStart, $plannedEnd);

            try {
                return ShiftAssignment::query()->create([
                    'branch_id' => $branch->getKey(),
                    'employee_id' => $employee->getKey(),
                    'work_shift_id' => $shift->getKey(),
                    'work_date' => $workDate,
                    'shift_name' => $shift->name,
                    'planned_start_at' => $plannedStart,
                    'planned_end_at' => $plannedEnd,
                    'shift_value' => $shift->shift_value,
                    'grace_minutes' => $shift->grace_minutes,
                    'early_check_in_minutes' => $shift->early_check_in_minutes,
                    'note' => $note,
                    'created_by' => $actor?->getKey(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'work_shift_id' => 'Nhân viên này đã được phân đúng ca đó trong ngày.',
                ]);
            }
        });
    }

    /** A branch may only roster its own templates and the shared ones. */
    private function assertTemplateUsable(Branch $branch, WorkShift $shift): void
    {
        if (! $shift->is_active) {
            throw ValidationException::withMessages([
                'work_shift_id' => 'Ca này đã ngừng sử dụng nên không thể phân mới.',
            ]);
        }

        if ($shift->branch_id !== null && (int) $shift->branch_id !== (int) $branch->getKey()) {
            throw ValidationException::withMessages([
                'work_shift_id' => 'Ca này thuộc chi nhánh khác.',
            ]);
        }
    }

    /**
     * A closed account is not somebody who can turn up.
     *
     * Deactivating an account does not end the branch postings behind it, so
     * without this the person still looks rosterable on any date those
     * postings cover — and a form opened before they left would still submit.
     */
    private function assertEmployeeStillWorks(User $employee): void
    {
        if ($employee->is_active) {
            return;
        }

        throw ValidationException::withMessages([
            'employee_id' => 'Tài khoản nhân viên đã ngừng hoạt động nên không thể phân ca.',
        ]);
    }

    /** Somebody can only be rostered where they are actually posted that day. */
    private function assertEmployeePostedToBranch(Branch $branch, User $employee, string $workDate): void
    {
        if ($employee->canAccessBranch($branch, $workDate)) {
            return;
        }

        throw ValidationException::withMessages([
            'employee_id' => 'Nhân viên chưa được phân công về chi nhánh này vào ngày đã chọn.',
        ]);
    }

    /**
     * Two shifts may not cover the same minute for the same person.
     *
     * Comparing the snapshot windows rather than the dates is what makes this
     * correct for an overnight shift, which belongs to one business date but
     * runs into the next one.
     */
    private function assertNoOverlap(User $employee, mixed $plannedStart, mixed $plannedEnd): void
    {
        $clash = ShiftAssignment::query()
            ->forEmployee($employee)
            ->overlapping($plannedStart, $plannedEnd)
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                'work_shift_id' => 'Ca này trùng giờ với một ca khác đã phân cho nhân viên.',
            ]);
        }
    }
}
