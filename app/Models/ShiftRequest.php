<?php

namespace App\Models;

use App\Enums\LeaveEntitlement;
use App\Enums\ShiftRequestStatus;
use App\Enums\ShiftRequestType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'type',
    'status',
    'branch_id',
    'requester_id',
    'recipient_id',
    'shift_assignment_id',
    'counter_shift_assignment_id',
    'work_date',
    'reason',
    'leave_entitlement',
    'processed_by',
    'processed_at',
])]
class ShiftRequest extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ShiftRequestType::class,
            'status' => ShiftRequestStatus::class,
            'work_date' => 'date',
            'leave_entitlement' => LeaveEntitlement::class,
            'processed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function shiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class);
    }

    public function counterShiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'counter_shift_assignment_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ShiftRequestHistory::class)->orderBy('id');
    }

    public function replacement(): HasOne
    {
        return $this->hasOne(ShiftReplacement::class);
    }

    public function isLeave(): bool
    {
        return $this->type === ShiftRequestType::Leave;
    }

    /**
     * An approved absence that still leaves the shift uncovered.
     *
     * The board asks this to decide both whether to warn about the gap and
     * whether to offer the stand-in picker, so the two can never disagree.
     */
    public function awaitingReplacement(): bool
    {
        return $this->isLeave()
            && $this->status === ShiftRequestStatus::Approved
            && $this->replacement === null;
    }

    /**
     * Đơn đang chờ chính người quản lý làm một việc gì đó.
     *
     * Huy hiệu trên menu và bộ lọc trên trang dùng chung đúng định nghĩa này.
     * Tách ra hai nơi thì huy hiệu báo ba việc còn bộ lọc mở ra bốn dòng, và
     * không ai biết bên nào đúng.
     */
    public function scopeNeedingAction(Builder $query): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->whereIn('status', [ShiftRequestStatus::PendingApproval, ShiftRequestStatus::RecipientConfirmed])
            ->orWhere(fn (Builder $uncovered) => $uncovered
                ->where('type', ShiftRequestType::Leave)
                ->where('status', ShiftRequestStatus::Approved)
                ->whereDoesntHave('replacement')));
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (ShiftRequestStatus $status): string => $status->value,
            array_filter(ShiftRequestStatus::cases(), fn (ShiftRequestStatus $status): bool => $status->isOpen()),
        ));
    }
}
