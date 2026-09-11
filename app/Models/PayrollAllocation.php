<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The slice of one payroll that a single branch is responsible for. */
#[Fillable([
    'payroll_id',
    'branch_id',
    'base_salary_amount',
    'shift_count',
    'shift_pay',
    'attendance_bonus',
    'regular_revenue',
    'regular_commission_pay',
    'overtime_revenue',
    'overtime_commission_pay',
    'daily_kpi_bonus',
    'bill_kpi_bonus',
    'allowance_total',
    'bonus_total',
    'deduction_total',
    'subtotal',
])]
class PayrollAllocation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'base_salary_amount' => 'decimal:2',
            'shift_count' => 'decimal:2',
            'shift_pay' => 'decimal:2',
            'attendance_bonus' => 'decimal:2',
            'regular_revenue' => 'decimal:2',
            'regular_commission_pay' => 'decimal:2',
            'overtime_revenue' => 'decimal:2',
            'overtime_commission_pay' => 'decimal:2',
            'daily_kpi_bonus' => 'decimal:2',
            'bill_kpi_bonus' => 'decimal:2',
            'allowance_total' => 'decimal:2',
            'bonus_total' => 'decimal:2',
            'deduction_total' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    public function dailyKpiResults(): HasMany
    {
        return $this->hasMany(DailyKpiResult::class);
    }
}
