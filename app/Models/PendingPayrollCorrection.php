<?php

namespace App\Models;

use App\Enums\PayrollAdjustmentDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money that has to be clawed back or paid out in a later period because the
 * payroll it belongs to was already closed.
 */
#[Fillable([
    'employee_id',
    'branch_id',
    'direction',
    'amount',
    'description',
    'source_type',
    'source_id',
    'origin_payroll_id',
    'applied_payroll_id',
    'applied_at',
])]
class PendingPayrollCorrection extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'direction' => PayrollAdjustmentDirection::class,
            'amount' => 'decimal:2',
            'applied_at' => 'datetime',
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

    public function appliedPayroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'applied_payroll_id');
    }

    /** Corrections not yet carried into a payroll, or carried into this one. */
    public function scopeAvailableFor(Builder $query, int $payrollId): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->whereNull('applied_payroll_id')
            ->orWhere('applied_payroll_id', $payrollId));
    }
}
