<?php

namespace Database\Factories;

use App\Enums\StockTransferStatus;
use App\Models\Branch;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'number' => DocumentNumber::forStockTransfer(),
            'source_branch_id' => Branch::factory(),
            'destination_branch_id' => Branch::factory(),
            'status' => StockTransferStatus::Draft,
            'created_by' => User::factory(),
            'note' => null,
        ];
    }
}
