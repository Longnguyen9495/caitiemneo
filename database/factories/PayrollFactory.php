<?php

namespace Database\Factories;

use App\Enums\PayrollStatus;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payroll>
 */
class PayrollFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'employee_id' => User::factory(),
            'created_by' => null,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'base_salary' => 0,
            'shift_rate' => 0,
            'shift_count' => 0,
            'shift_pay' => 0,
            'commission_pay' => 0,
            'adjustment' => 0,
            'deduction' => 0,
            'total' => 0,
            'status' => PayrollStatus::Draft,
            'paid_at' => null,
            'note' => null,
        ];
    }

    public function finalized(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PayrollStatus::Finalized,
            'finalized_at' => now(),
        ]);
    }
}
