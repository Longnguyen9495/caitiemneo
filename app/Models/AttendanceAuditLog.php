<?php

namespace App\Models;

use App\Enums\AttendanceAuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One append-only entry in the attendance audit trail.
 *
 * Nothing in the application updates or deletes these rows; a correction is
 * itself a new entry.
 */
#[Fillable([
    'attendance_record_id',
    'employee_id_snapshot',
    'work_date_snapshot',
    'shift_name_snapshot',
    'branch_id',
    'actor_id',
    'action',
    'reason',
    'before',
    'after',
])]
class AttendanceAuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'action' => AttendanceAuditAction::class,
            'work_date_snapshot' => 'date',
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Fields that actually moved, for rendering a compact diff.
     *
     * @return array<string, array{before: mixed, after: mixed}>
     */
    public function changes(): array
    {
        $before = $this->before ?? [];
        $after = $this->after ?? [];
        $diff = [];

        foreach (array_keys($before + $after) as $key) {
            $from = $before[$key] ?? null;
            $to = $after[$key] ?? null;

            if ($from !== $to) {
                $diff[$key] = ['before' => $from, 'after' => $to];
            }
        }

        return $diff;
    }
}
