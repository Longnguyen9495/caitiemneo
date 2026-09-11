<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\WorkShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkShift>
 */
class WorkShiftFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'branch_id' => BranchFactory::existingOrNew(),
            'name' => 'Ca '.fake()->unique()->numerify('##'),
            'starts_at' => '09:00:00',
            'ends_at' => '19:00:00',
            'shift_value' => 1,
            'grace_minutes' => 0,
            'early_check_in_minutes' => null,
            'is_active' => true,
        ];
    }

    /** A template every branch may roster from. */
    public function shared(): static
    {
        return $this->state(fn (array $attributes): array => ['branch_id' => null]);
    }

    public function atBranch(int|Branch $branch): static
    {
        return $this->state(fn (array $attributes): array => [
            'branch_id' => $branch instanceof Branch ? $branch->getKey() : $branch,
        ]);
    }

    public function spanning(string $startsAt, string $endsAt): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    /** 21:00 to 05:00: ends before it starts, so it rolls to the next day. */
    public function overnight(): static
    {
        return $this->spanning('21:00:00', '05:00:00');
    }

    public function grace(int $minutes): static
    {
        return $this->state(fn (array $attributes): array => ['grace_minutes' => $minutes]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
