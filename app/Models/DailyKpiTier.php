<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One revenue band of the daily KPI ladder.
 *
 * `revenue_to` is exclusive and a null upper bound means "and above". Only the
 * single highest band an employee reaches on a day pays out; bands never stack.
 */
#[Fillable(['payroll_policy_id', 'revenue_from', 'revenue_to', 'reward_amount', 'sort_order'])]
class DailyKpiTier extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'revenue_from' => 'decimal:2',
            'revenue_to' => 'decimal:2',
            'reward_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(PayrollPolicy::class, 'payroll_policy_id');
    }
}
