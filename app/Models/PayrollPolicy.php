<?php

namespace App\Models;

use App\Actions\Payrolls\CalculatePayrollAction;
use App\Enums\RoundingRule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The dated rule book the payroll engine reads: attendance bonus, bill KPI
 * targets, rounding, and the daily revenue tiers hanging off it.
 *
 * Nothing in {@see CalculatePayrollAction} hard-codes an
 * amount; every number comes from the policy that was in force on the day.
 */
#[Fillable([
    'branch_id',
    'name',
    'effective_from',
    'effective_to',
    'attendance_bonus_amount',
    'allowed_absence_days',
    'excused_leave_counts_as_absence',
    'late_counts_as_absence',
    'required_bill_count',
    'bill_kpi_reward_amount',
    'missing_bill_penalty_amount',
    'rounding_rule',
    'is_active',
    'created_by',
])]
class PayrollPolicy extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'attendance_bonus_amount' => 'decimal:2',
            'allowed_absence_days' => 'integer',
            'excused_leave_counts_as_absence' => 'boolean',
            'late_counts_as_absence' => 'boolean',
            'required_bill_count' => 'integer',
            'bill_kpi_reward_amount' => 'decimal:2',
            'missing_bill_penalty_amount' => 'decimal:2',
            'rounding_rule' => RoundingRule::class,
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function dailyKpiTiers(): HasMany
    {
        return $this->hasMany(DailyKpiTier::class)->orderBy('sort_order')->orderBy('revenue_from');
    }

    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(EmployeePolicyAssignment::class);
    }

    public function scopeEffectiveOn(Builder $query, mixed $date): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $date));
    }
}
