<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => 'CN-'.fake()->unique()->numerify('##'),
            'name' => 'Chi nhánh '.fake()->unique()->city(),
            'address' => fake()->address(),
            'phone' => fake()->numerify('028########'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    /**
     * The branch a related factory should attach to.
     *
     * Reusing the first existing branch keeps single-shop test setups on one
     * branch instead of silently spawning a new one per record.
     */
    /** Always returns a concrete branch id, creating the first one if needed. */
    public static function resolveId(): int
    {
        return Branch::query()->orderBy('id')->value('id')
            ?? self::new()->create()->getKey();
    }

    public static function existingOrNew(): int|self
    {
        return Branch::query()->orderBy('id')->value('id') ?? self::new();
    }
}
