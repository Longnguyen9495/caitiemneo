<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceAuditAction;
use App\Models\AttendanceAuditLog;
use App\Models\AttendanceRecord;
use App\Models\User;

/**
 * Writes the attendance audit trail.
 *
 * Kept as one service so every caller records the same shape, and so the
 * decision about what is and is not stored (coordinates are not) lives in one
 * place.
 */
final class AttendanceAuditor
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        AttendanceRecord $record,
        ?User $actor,
        AttendanceAuditAction $action,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
    ): AttendanceAuditLog {
        return AttendanceAuditLog::query()->create([
            'attendance_record_id' => $record->getKey(),
            // Snapshotted so the entry stays readable once the shift it
            // describes has been deleted and the relation is nulled out.
            'employee_id_snapshot' => $record->employee_id,
            'work_date_snapshot' => $record->work_date,
            'shift_name_snapshot' => $record->shift_name,
            'branch_id' => $record->branch_id,
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'reason' => $reason,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
