<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(100, 1000) * 1000;

        return [
            'invoice_id' => Invoice::factory(),
            'service_id' => null,
            'employee_id' => null,
            'name' => 'Dịch vụ '.fake()->word(),
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice,
            'commission_rate' => 0,
            'commission_amount' => 0,
        ];
    }
}
