<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dated set of pay rates for one employee, optionally scoped to one branch.
 *
 * A branch-specific profile wins over a shop-wide one for the same date.
 */
#[Fillable([
    'user_id',
    'branch_id',
    'base_salary',
    'shift_rate',
    'regular_commission_rate',
    'overtime_commission_rate',
    'effective_from',
    'effective_to',
    'created_by',
])]
class EmployeeCompensationProfile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'shift_rate' => 'decimal:2',
            'regular_commission_rate' => 'decimal:2',
            'overtime_commission_rate' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeEffectiveOn(Builder $query, mixed $date): Builder
    {
        return $query
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $date));
    }
}
