<?php

namespace Database\Factories;

use App\Models\EmployeeCompensationProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeCompensationProfile>
 */
class EmployeeCompensationProfileFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'branch_id' => null,
            'base_salary' => 0,
            'shift_rate' => 0,
            'regular_commission_rate' => 0,
            'overtime_commission_rate' => 0,
            'effective_from' => '2000-01-01',
            'effective_to' => null,
        ];
    }
}
