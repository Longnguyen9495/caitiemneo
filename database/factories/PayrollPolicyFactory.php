<?php

namespace Database\Factories;

use App\Enums\RoundingRule;
use App\Models\PayrollPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollPolicy>
 */
class PayrollPolicyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'branch_id' => null,
            'name' => 'Chính sách lương chuẩn',
            'effective_from' => '2000-01-01',
            'effective_to' => null,
            'attendance_bonus_amount' => 200000,
            'allowed_absence_days' => 2,
            'excused_leave_counts_as_absence' => false,
            'late_counts_as_absence' => false,
            'required_bill_count' => null,
            'bill_kpi_reward_amount' => null,
            'missing_bill_penalty_amount' => null,
            'rounding_rule' => RoundingRule::CeilTo1000,
            'is_active' => true,
        ];
    }

    /** The two revenue bands the shop currently runs. */
    public function withStandardKpiTiers(): static
    {
        return $this->afterCreating(function (PayrollPolicy $policy): void {
            $policy->dailyKpiTiers()->createMany([
                ['revenue_from' => 1000000, 'revenue_to' => 1700000, 'reward_amount' => 50000, 'sort_order' => 1],
                ['revenue_from' => 1700000, 'revenue_to' => null, 'reward_amount' => 100000, 'sort_order' => 2],
            ]);
        });
    }

    public function withBillKpi(int $required = 16, int $reward = 300000, ?int $penalty = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'required_bill_count' => $required,
            'bill_kpi_reward_amount' => $reward,
            'missing_bill_penalty_amount' => $penalty,
        ]);
    }
}
