<?php

namespace Database\Seeders;

use App\Enums\RoundingRule;
use App\Models\PayrollPolicy;
use Illuminate\Database\Seeder;

/**
 * The company-wide pay rule book and its daily revenue ladder.
 *
 * These numbers are configuration, not constants in code: changing the bonus or
 * a KPI band is a data edit with an effective date, never a deploy.
 */
class PayrollPolicySeeder extends Seeder
{
    public function run(): void
    {
        $policy = PayrollPolicy::query()->updateOrCreate(
            ['branch_id' => null, 'name' => 'Chính sách lương chuẩn'],
            [
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'attendance_bonus_amount' => 200000,
                'allowed_absence_days' => 2,
                'excused_leave_counts_as_absence' => false,
                'late_counts_as_absence' => false,
                'required_bill_count' => 16,
                'bill_kpi_reward_amount' => 300000,
                'missing_bill_penalty_amount' => null,
                'rounding_rule' => RoundingRule::CeilTo1000,
                'is_active' => true,
            ],
        );

        $tiers = [
            ['revenue_from' => 1000000, 'revenue_to' => 1700000, 'reward_amount' => 50000, 'sort_order' => 1],
            ['revenue_from' => 1700000, 'revenue_to' => null, 'reward_amount' => 100000, 'sort_order' => 2],
        ];

        foreach ($tiers as $tier) {
            $policy->dailyKpiTiers()->updateOrCreate(
                ['revenue_from' => $tier['revenue_from']],
                $tier,
            );
        }
    }
}
