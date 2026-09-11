<?php

namespace App\Enums;

/**
 * How the final take-home amount of a payroll is rounded.
 *
 * The shop always rounds the final total UP to the next 1.000 d, so the rule is
 * a ceiling on a 1.000 d step, never a nearest-value rounding.
 */
enum RoundingRule: string
{
    case None = 'none';
    case CeilTo1000 = 'ceil_to_1000';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Không làm tròn',
            self::CeilTo1000 => 'Làm tròn lên 1.000 đ',
        };
    }

    /** Step of the rounding expressed in dong; zero means no rounding. */
    public function stepInDong(): int
    {
        return match ($this) {
            self::None => 0,
            self::CeilTo1000 => 1000,
        };
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
