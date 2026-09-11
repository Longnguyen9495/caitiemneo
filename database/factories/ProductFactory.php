<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Vật tư '.fake()->unique()->word(),
            'sku' => fake()->unique()->bothify('SKU-####'),
            'unit' => 'chai',
            'cost_price' => fake()->numberBetween(20, 300) * 1000,
            'minimum_stock' => 5,
            'is_active' => true,
        ];
    }
}
