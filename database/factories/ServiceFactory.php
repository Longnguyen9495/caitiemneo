<?php

namespace Database\Factories;

use App\Enums\ServiceCategory;
use App\Enums\ServiceUnit;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Dịch vụ '.fake()->unique()->word(),
            'description' => fake()->sentence(),
            'category' => ServiceCategory::BasicNail,
            'unit' => ServiceUnit::Set,
            'price' => fake()->numberBetween(100, 2000) * 1000,
            'price_min' => null,
            'price_max' => null,
            'display_order' => 0,
            'is_active' => true,
        ];
    }

    /** Dịch vụ chốt giá theo độ khó, ví dụ vẽ móng hay charm đá. */
    public function priced(int $min, int $max): static
    {
        return $this->state(fn (array $attributes): array => [
            'price' => $min,
            'price_min' => $min,
            'price_max' => $max,
        ]);
    }

    public function inCategory(ServiceCategory $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => $category,
            'unit' => $category->defaultUnit(),
        ]);
    }

    /** Trang trí tính theo ngón chứ không theo bộ. */
    public function perFinger(): static
    {
        return $this->state(fn (array $attributes): array => ['unit' => ServiceUnit::Finger]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
