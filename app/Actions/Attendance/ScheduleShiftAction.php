<?php

namespace App\Actions\Attendance;

use App\Enums\AuditAction;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Audit\AuditRecorder;
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
    public function __construct(private AuditRecorder $auditor) {}

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
        $this->assertEmployeePostedToBranch($branch, $employee, $workDate);

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

    /**
     * Bỏ một ca đã phân.
     *
     * Xóa cứng nên bản chụp phải được ghi trước, ngay trong cùng transaction:
     * sau khi dòng biến mất thì không còn gì để chép lại, và một ca bị gỡ khỏi
     * lịch mà không ai biết là chuyện phải tra ngược được.
     *
     * @throws ValidationException
     */
    public function remove(ShiftAssignment $assignment, User $actor, ?string $reason = null): ShiftAssignment
    {
        // Ca đã có công là bằng chứng: bỏ lịch sẽ để lại dòng chấm công mồ côi.
        if ($assignment->attendanceRecord()->exists()) {
            throw ValidationException::withMessages([
                'work_shift_id' => 'Ca này đã có dữ liệu chấm công nên không thể bỏ phân ca. Hãy sửa bản ghi chấm công thay vì xóa lịch.',
            ]);
        }

        return DB::transaction(function () use ($assignment, $actor, $reason): ShiftAssignment {
            $this->auditor->record(
                subject: $assignment,
                actor: $actor,
                action: AuditAction::Deleted,
                before: $assignment->attributesToArray(),
                reason: $reason,
                branchId: $assignment->branch_id,
                label: $assignment->shift_name,
            );

            $assignment->delete();

            return $assignment;
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
            ->where('employee_id', $employee->getKey())
            ->where('planned_start_at', '<', $plannedEnd)
            ->where('planned_end_at', '>', $plannedStart)
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                'work_shift_id' => 'Ca này trùng giờ với một ca khác đã phân cho nhân viên.',
            ]);
        }
    }
}
