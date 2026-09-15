<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'branch_id' => BranchFactory::existingOrNew(),
            'number' => DocumentNumber::forInvoice(),
            'appointment_id' => null,
            'customer_id' => null,
            'employee_id' => null,
            'created_by' => User::factory(),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('09########'),
            'status' => InvoiceStatus::Draft,
            'payment_method' => null,
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'commission_rate' => 0,
            'commission_rate_source' => null,
            'paid_at' => null,
            'note' => null,
        ];
    }
}
