<?php

namespace App\Models;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\GpsVerification;
use App\Enums\OvertimeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One worked (or missed) shift for one employee on one date.
 *
 * Rows arrive two ways: an employee clocking in from their phone, which
 * carries GPS evidence, or a manager filling in an exception by hand, which
 * carries a reason and an audit entry. `source` says which.
 *
 * Only the GPS evidence columns and the manual `note` are mass-assignable
 * through the admin form; lateness, overtime and approval are computed or set
 * by their own actions so no form post can move them.
 */
#[Fillable([
    'branch_id',
    'shift_assignment_id',
    'employee_id',
    'work_date',
    'shift_name',
    'shift_value',
    'status',
    'checked_in_at',
    'checked_out_at',
    'note',
])]
class AttendanceRecord extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'shift_value' => 'decimal:2',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'status' => AttendanceStatus::class,
            'source' => AttendanceSource::class,
            'is_self_recorded' => 'boolean',
            'check_in_latitude' => 'decimal:7',
            'check_in_longitude' => 'decimal:7',
            'check_in_accuracy_meters' => 'integer',
            'check_in_distance_meters' => 'integer',
            'check_in_verification' => GpsVerification::class,
            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',
            'check_out_accuracy_meters' => 'integer',
            'check_out_distance_meters' => 'integer',
            'check_out_verification' => GpsVerification::class,
            'late_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'approved_overtime_minutes' => 'integer',
            'overtime_status' => OvertimeStatus::class,
            'overtime_approved_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function shiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class);
    }

    public function overtimeApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overtime_approved_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AttendanceAuditLog::class)->latest('id');
    }

    /** Shifts that count towards payroll shift pay. */
    public function scopePayable(Builder $query): Builder
    {
        return $query->whereIn('status', AttendanceStatus::payableValues());
    }

    /** An overrun that a reviewer still has to decide on. */
    public function scopePendingOvertime(Builder $query): Builder
    {
        return $query->where('overtime_status', OvertimeStatus::Pending->value);
    }

    /** Clocked in but never clocked out. */
    public function scopeMissingCheckOut(Builder $query): Builder
    {
        return $query->whereNotNull('checked_in_at')->whereNull('checked_out_at');
    }

    /**
     * GPS rows whose location check did not pass.
     *
     * A refused clock event is never saved, so this is normally empty; it
     * catches rows left behind when a branch's coordinates or radius change.
     */
    /**
     * Ca do chính người đó tự ghi bằng tay.
     *
     * Quản lý đã bị chặn tự ghi, nên thực tế đây là ca của owner: giữ lại để
     * hàng đợi duyệt đưa ra trước mắt người xem thay vì để trôi qua.
     */
    public function scopeSelfRecorded(Builder $query): Builder
    {
        return $query->where('is_self_recorded', true);
    }

    public function scopeGpsNeedsReview(Builder $query): Builder
    {
        return $query
            ->where('source', AttendanceSource::Gps->value)
            ->where(fn (Builder $inner) => $inner
                ->whereIn('check_in_verification', GpsVerification::needsReviewValues())
                ->orWhereIn('check_out_verification', GpsVerification::needsReviewValues()));
    }

    public function isOpenSession(): bool
    {
        return $this->checked_in_at !== null && $this->checked_out_at === null;
    }

    /** Minutes a payroll policy would be allowed to pay for, once one exists. */
    public function payableOvertimeMinutes(): int
    {
        return $this->overtime_status === OvertimeStatus::Approved
            ? (int) $this->approved_overtime_minutes
            : 0;
    }

    /**
     * The values the audit trail records for this row.
     *
     * Coordinates are deliberately excluded: the audit log answers "what
     * changed about this shift", not "where was this person standing".
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'work_date' => $this->work_date?->toDateString(),
            'shift_name' => $this->shift_name,
            'shift_value' => (string) $this->shift_value,
            'status' => $this->status?->value,
            'checked_in_at' => $this->checked_in_at?->toDateTimeString(),
            'checked_out_at' => $this->checked_out_at?->toDateTimeString(),
            'late_minutes' => (int) $this->late_minutes,
            'overtime_minutes' => (int) $this->overtime_minutes,
            'approved_overtime_minutes' => (int) $this->approved_overtime_minutes,
            'overtime_status' => $this->overtime_status?->value,
            'note' => $this->note,
        ];
    }
}
