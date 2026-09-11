<?php

namespace App\Enums;

enum PayrollAdjustmentDirection: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';

    public function label(): string
    {
        return match ($this) {
            self::Earning => 'Cộng',
            self::Deduction => 'Trừ',
        };
    }

    /** Amounts are always stored positive; the direction carries the sign. */
    public function sign(): int
    {
        return $this === self::Earning ? 1 : -1;
    }

    public function tone(): string
    {
        return $this === self::Earning ? 'is-success' : 'is-danger';
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
