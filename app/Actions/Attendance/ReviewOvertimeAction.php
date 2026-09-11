<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceAuditAction;
use App\Enums\OvertimeStatus;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\Attendance\AttendanceAuditor;
use App\Services\Payroll\PayrollLockGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A manager deciding what to do with a detected overrun.
 *
 * The decision is recorded as minutes only. There is no overtime pay policy in
 * the system yet, and inventing a rate here would quietly create money that
 * nobody agreed to, so the approved minutes simply sit on the row waiting for
 * a policy to consume them.
 */
class ReviewOvertimeAction
{
    public function __construct(
        private AttendanceAuditor $auditor,
        private PayrollLockGuard $payrollLock,
    ) {}

    /** @throws ValidationException */
    public function handle(
        AttendanceRecord $record,
        User $reviewer,
        OvertimeStatus $decision,
        ?int $approvedMinutes,
        ?string $note = null,
    ): AttendanceRecord {
        if (! in_array($decision, [OvertimeStatus::Approved, OvertimeStatus::Rejected], true)) {
            throw ValidationException::withMessages([
                'overtime_status' => 'Chỉ có thể duyệt hoặc từ chối tăng ca.',
            ]);
        }

        // Approving minutes is a pay-affecting change, so a closed payroll
        // blocks it exactly like an edit of the hours would.
        $this->payrollLock->assertUnlocked((int) $record->employee_id, $record->work_date, 'overtime_status');

        return DB::transaction(function () use ($record, $reviewer, $decision, $approvedMinutes, $note): AttendanceRecord {
            $locked = AttendanceRecord::query()->lockForUpdate()->findOrFail($record->getKey());

            if ($locked->overtime_minutes <= 0) {
                throw ValidationException::withMessages([
                    'overtime_status' => 'Ca này không có thời gian vượt ca để duyệt.',
                ]);
            }

            $minutes = $decision === OvertimeStatus::Approved
                ? $this->resolveApprovedMinutes($locked, $approvedMinutes)
                : 0;

            $before = $locked->auditSnapshot();

            $locked->forceFill([
                'overtime_status' => $decision,
                'approved_overtime_minutes' => $minutes,
                'overtime_approved_by' => $reviewer->getKey(),
                'overtime_approved_at' => now(),
                'overtime_approval_note' => $note,
            ])->save();

            $this->auditor->record(
                $locked,
                $reviewer,
                $decision === OvertimeStatus::Approved
                    ? AttendanceAuditAction::OvertimeApproved
                    : AttendanceAuditAction::OvertimeRejected,
                $before,
                $locked->auditSnapshot(),
                $note,
            );

            return $locked;
        });
    }

    /**
     * A reviewer may trim the detected overrun but never inflate it.
     *
     * Paying for more minutes than the clock recorded would need a different
     * kind of entry, with its own reason, not a quiet edit of this number.
     */
    private function resolveApprovedMinutes(AttendanceRecord $record, ?int $approvedMinutes): int
    {
        $detected = (int) $record->overtime_minutes;
        $minutes = $approvedMinutes ?? $detected;

        if ($minutes < 0) {
            throw ValidationException::withMessages([
                'approved_overtime_minutes' => 'Số phút duyệt không thể là số âm.',
            ]);
        }

        if ($minutes > $detected) {
            throw ValidationException::withMessages([
                'approved_overtime_minutes' => sprintf(
                    'Chỉ có thể duyệt tối đa %d phút, đúng bằng thời gian hệ thống ghi nhận.',
                    $detected,
                ),
            ]);
        }

        return $minutes;
    }
}
