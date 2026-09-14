<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use App\Enums\RoundingRule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'employee_id',
    'paying_branch_id',
    'payroll_policy_id',
    'created_by',
    'period_start',
    'period_end',
    'base_salary',
    'calendar_days',
    'required_work_days',
    'paid_leave_days',
    'unpaid_leave_days',
    'daily_base_salary_rate',
    'unpaid_leave_deduction',
    'worked_paid_leave_days',
    'worked_paid_leave_bonus_rate',
    'worked_paid_leave_bonus',
    'net_base_salary',
    'shift_rate',
    'shift_count',
    'shift_pay',
    'commission_pay',
    'attendance_bonus',
    'regular_revenue',
    'regular_commission_pay',
    'overtime_revenue',
    'overtime_commission_pay',
    'daily_kpi_bonus',
    'bill_kpi_bonus',
    'qualified_bill_count',
    'bill_kpi_achieved',
    'allowance_total',
    'bonus_total',
    'adjustment',
    'deduction',
    'total',
    'calculated_total',
    'approved_manual_adjustment',
    'unrounded_final_total',
    'rounding_adjustment',
    'final_total',
    'rounding_rule',
    'variance_reason',
    'approved_by',
    'approved_at',
    'status',
    'paid_at',
    'finalized_at',
    'finalized_by',
    'cancelled_at',
    'cancelled_by',
    'note',
])]
class Payroll extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'base_salary' => 'decimal:2',
            'calendar_days' => 'integer',
            'required_work_days' => 'integer',
            'paid_leave_days' => 'integer',
            'unpaid_leave_days' => 'integer',
            'daily_base_salary_rate' => 'decimal:2',
            'unpaid_leave_deduction' => 'decimal:2',
            'worked_paid_leave_days' => 'integer',
            'worked_paid_leave_bonus_rate' => 'decimal:2',
            'worked_paid_leave_bonus' => 'decimal:2',
            'net_base_salary' => 'decimal:2',
            'shift_rate' => 'decimal:2',
            'shift_count' => 'decimal:2',
            'shift_pay' => 'decimal:2',
            'commission_pay' => 'decimal:2',
            'adjustment' => 'decimal:2',
            'deduction' => 'decimal:2',
            'total' => 'decimal:2',
            'attendance_bonus' => 'decimal:2',
            'regular_revenue' => 'decimal:2',
            'regular_commission_pay' => 'decimal:2',
            'overtime_revenue' => 'decimal:2',
            'overtime_commission_pay' => 'decimal:2',
            'daily_kpi_bonus' => 'decimal:2',
            'bill_kpi_bonus' => 'decimal:2',
            'qualified_bill_count' => 'integer',
            'bill_kpi_achieved' => 'boolean',
            'allowance_total' => 'decimal:2',
            'bonus_total' => 'decimal:2',
            'calculated_total' => 'decimal:2',
            'approved_manual_adjustment' => 'decimal:2',
            'unrounded_final_total' => 'decimal:2',
            'rounding_adjustment' => 'decimal:2',
            'final_total' => 'decimal:2',
            'rounding_rule' => RoundingRule::class,
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'finalized_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => PayrollStatus::class,
        ];
    }

    public function payingBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'paying_branch_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(PayrollPolicy::class, 'payroll_policy_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PayrollAllocation::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    public function dailyKpiResults(): HasMany
    {
        return $this->hasMany(DailyKpiResult::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }
}
