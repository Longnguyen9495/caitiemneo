<?php

namespace App\Enums;

enum CashTransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Khoản thu',
            self::Expense => 'Khoản chi',
        };
    }

    public function tone(): string
    {
        return $this === self::Income ? 'is-success' : 'is-danger';
    }

    /** Sign applied to the stored, always positive, amount when computing a balance. */
    public function sign(): int
    {
        return $this === self::Income ? 1 : -1;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
