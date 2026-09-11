<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * A counter shared by every instance of the factory.
     *
     * `fake()->unique()` only de-duplicates within one faker instance, and a
     * two-digit code gives just a hundred values, so a suite that builds a few
     * branches per test used to collide on `branches.code` at random. Counting
     * in PHP makes the code unique for the whole process instead.
     */
    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = ++self::$sequence;

        return [
            'code' => sprintf('CN-%03d', $number),
            'name' => 'Chi nhánh '.fake()->city().' '.$number,
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
