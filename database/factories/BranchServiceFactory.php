<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchService>
 */
class BranchServiceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'service_id' => Service::factory(),
            'price' => fake()->numberBetween(100, 2000) * 1000,
            'duration_minutes' => 60,
            'commission_rate' => null,
            'overtime_commission_rate' => null,
            'is_active' => true,
        ];
    }
}
