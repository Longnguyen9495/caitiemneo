<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'branch_id',
    'work_shift_id',
    'effective_from',
    'effective_to',
    'created_by',
])]
class EmployeeFixedShift extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Plans in force at any point between two dates.
     *
     * Asked whenever a whole month is in view — both to list the plans and to
     * roster from them — so the window arithmetic lives here rather than being
     * spelled out again at each call site.
     */
    public function scopeEffectiveBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query
            ->whereDate('effective_from', '<=', $to)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $from));
    }

    /**
     * Whether the plan has already governed a real day.
     *
     * A plan whose start is still ahead has produced nothing, so it can be
     * dropped; one that has begun is history and gets closed with a date.
     */
    public function hasTakenEffect(): bool
    {
        return $this->effective_from->toDateString() <= now()->toDateString();
    }

    /** The end of the window, or null while it is still open. */
    public function isOpenEnded(): bool
    {
        return $this->effective_to === null;
    }

    /** Whether this plan is in force on one business date. */
    public function coversDate(string $date): bool
    {
        return $this->effective_from->toDateString() <= $date
            && ($this->effective_to === null || $this->effective_to->toDateString() >= $date);
    }
}
