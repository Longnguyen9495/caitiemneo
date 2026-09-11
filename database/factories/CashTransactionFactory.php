<?php

namespace Database\Factories;

use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\PaymentMethod;
use App\Models\CashTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashTransaction>
 */
class CashTransactionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'branch_id' => BranchFactory::existingOrNew(),
            'created_by' => User::factory(),
            'type' => CashTransactionType::Expense,
            'category' => CashTransactionCategory::OtherExpense,
            'amount' => fake()->numberBetween(50, 500) * 1000,
            'payment_method' => PaymentMethod::Cash,
            'reference' => null,
            'note' => fake()->sentence(),
            'occurred_at' => now(),
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CashTransactionType::Income,
            'category' => CashTransactionCategory::OtherIncome,
        ]);
    }
}
