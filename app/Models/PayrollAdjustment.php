<?php

namespace App\Models;

use App\Enums\PayrollAdjustmentCategory;
use App\Enums\PayrollAdjustmentDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One itemised line of a payroll: a bonus, an allowance, a penalty, an advance
 * or a correction carried over from a closed period.
 *
 * Amounts are always positive; `direction` carries the sign. Rows the engine
 * owns are flagged `is_automatic` and are replaced wholesale on recalculation,
 * while hand-entered rows survive untouched.
 */
#[Fillable([
    'payroll_id',
    'payroll_allocation_id',
    'branch_id',
    'category',
    'direction',
    'amount',
    'description',
    'source_type',
    'source_id',
    'is_automatic',
    'created_by',
    'approved_by',
    'approved_at',
])]
class PayrollAdjustment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => PayrollAdjustmentCategory::class,
            'direction' => PayrollAdjustmentDirection::class,
            'amount' => 'decimal:2',
            'is_automatic' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(PayrollAllocation::class, 'payroll_allocation_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeAutomatic(Builder $query): Builder
    {
        return $query->where('is_automatic', true);
    }

    public function scopeManual(Builder $query): Builder
    {
        return $query->where('is_automatic', false);
    }
}
