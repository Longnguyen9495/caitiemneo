<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceAuditAction;
use App\Enums\AttendanceSource;
use App\Enums\OvertimeStatus;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\Attendance\AttendanceAuditor;
use App\Services\Payroll\PayrollLockGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The manager's exception route: a forgotten clock-in, a leave day, a fix.
 *
 * This is deliberately kept alongside GPS rather than replaced by it. What it
 * adds over a plain create/update is the three things an exception needs to be
 * trustworthy: a reason, a before/after audit entry, and a refusal when the
 * shift already sits inside a closed payroll.
 */
class SaveManualAttendanceAction
{
    public function __construct(
        private AttendanceAuditor $auditor,
        private PayrollLockGuard $payrollLock,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function create(array $attributes, User $actor, string $reason): AttendanceRecord
    {
        $this->payrollLock->assertUnlocked((int) $attributes['employee_id'], $attributes['work_date']);

        return DB::transaction(function () use ($attributes, $actor, $reason): AttendanceRecord {
            $record = AttendanceRecord::query()->create($attributes);

            $record->forceFill([
                'source' => AttendanceSource::Manual,
                'overtime_status' => OvertimeStatus::None,
                // Chỉ owner mới tới được đây với ca của chính mình (policy chặn
                // quản lý), và khi đó dòng được đánh dấu để hàng đợi duyệt nêu lên.
                'is_self_recorded' => (int) $record->employee_id === (int) $actor?->getKey(),
            ])->save();

            $this->auditor->record(
                $record,
                $actor,
                AttendanceAuditAction::ManualCreate,
                null,
                $record->auditSnapshot(),
                $reason,
            );

            return $record;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function update(AttendanceRecord $record, array $attributes, User $actor, string $reason): AttendanceRecord
    {
        // Both the shift as it stands and the shift as it would become have to
        // be outside a closed payroll, otherwise a date edit could move hours
        // out of a period that has already been paid.
        $this->payrollLock->assertUnlocked((int) $record->employee_id, $record->work_date);
        $this->payrollLock->assertUnlocked((int) ($attributes['employee_id'] ?? $record->employee_id), $attributes['work_date'] ?? $record->work_date);

        return DB::transaction(function () use ($record, $attributes, $actor, $reason): AttendanceRecord {
            $locked = AttendanceRecord::query()->lockForUpdate()->findOrFail($record->getKey());
            $before = $locked->auditSnapshot();

            $locked->fill($attributes)->save();
            $this->refreshOvertime($locked);

            $this->auditor->record(
                $locked,
                $actor,
                AttendanceAuditAction::ManualUpdate,
                $before,
                $locked->auditSnapshot(),
                $reason,
            );

            return $locked;
        });
    }

    /** @throws ValidationException */
    public function delete(AttendanceRecord $record, User $actor, ?string $reason = null): void
    {
        $this->payrollLock->assertUnlocked((int) $record->employee_id, $record->work_date);

        DB::transaction(function () use ($record, $actor, $reason): void {
            // Logged before the delete so the cascade does not take the entry
            // with it; the surviving trail is the branch-level one.
            $this->auditor->record(
                $record,
                $actor,
                AttendanceAuditAction::ManualDelete,
                $record->auditSnapshot(),
                null,
                $reason,
            );

            $record->delete();
        });
    }

    /**
     * Keep the overrun in step when a manager edits the clock-out time.
     *
     * Any recalculated overrun goes back to pending: a previous approval was
     * given for a different number of minutes and must not carry over.
     */
    private function refreshOvertime(AttendanceRecord $record): void
    {
        $plannedEnd = $record->shiftAssignment?->planned_end_at;

        if ($plannedEnd === null) {
            return;
        }

        $checkedOut = $record->checked_out_at;
        $minutes = $checkedOut !== null && $checkedOut->gt($plannedEnd)
            ? (int) floor($plannedEnd->diffInSeconds($checkedOut) / 60)
            : 0;

        if ($minutes === (int) $record->overtime_minutes) {
            return;
        }

        $record->forceFill([
            'overtime_minutes' => $minutes,
            'approved_overtime_minutes' => 0,
            'overtime_status' => $minutes > 0 ? OvertimeStatus::Pending : OvertimeStatus::None,
            'overtime_approved_by' => null,
            'overtime_approved_at' => null,
            'overtime_approval_note' => null,
        ])->save();
    }
}
