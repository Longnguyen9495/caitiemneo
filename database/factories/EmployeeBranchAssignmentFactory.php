<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\EmployeeBranchAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeBranchAssignment>
 */
class EmployeeBranchAssignmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'is_primary' => true,
            'starts_on' => '2000-01-01',
            'ends_on' => null,
        ];
    }

    public function secondary(): static
    {
        return $this->state(fn (array $attributes): array => ['is_primary' => false]);
    }
}
