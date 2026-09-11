<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What one employee earned on one day at one branch, and which tier it hit. */
#[Fillable([
    'payroll_id',
    'payroll_allocation_id',
    'branch_id',
    'employee_id',
    'work_date',
    'eligible_revenue',
    'achieved_tier_id',
    'reward_amount',
    'source_policy_id',
])]
class DailyKpiResult extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'eligible_revenue' => 'decimal:2',
            'reward_amount' => 'decimal:2',
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(DailyKpiTier::class, 'achieved_tier_id');
    }
}
