<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\MonthlyPaidLeaveDay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MonthlyPaidLeaveDay> */
class MonthlyPaidLeaveDayFactory extends Factory
{
    protected $model = MonthlyPaidLeaveDay::class;

    public function definition(): array
    {
        return [
            'employee_id' => User::factory()->employee(),
            'branch_id' => Branch::factory(),
            'leave_date' => now()->startOfMonth()->addDays($this->faker->numberBetween(0, 20))->toDateString(),
            'scheduled_by' => User::factory()->owner(),
        ];
    }
}
