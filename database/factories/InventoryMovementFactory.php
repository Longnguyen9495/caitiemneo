<?php

namespace Database\Factories;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'branch_id' => BranchFactory::existingOrNew(),
            'product_id' => Product::factory(),
            'supplier_id' => null,
            'created_by' => User::factory(),
            'type' => InventoryMovementType::In,
            'quantity' => 10,
            'unit_cost' => 50000,
            'reference' => null,
            'note' => null,
            'occurred_at' => now(),
        ];
    }
}
