<?php

namespace App\Enums;

/**
 * Whether a revenue line was produced during normal opening hours or as an
 * approved out-of-hours job. The two carry different commission rates.
 */
enum WorkContext: string
{
    case Regular = 'regular';
    case Overtime = 'overtime';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Trong giờ',
            self::Overtime => 'Ngoài giờ',
        };
    }

    public function tone(): string
    {
        return $this === self::Regular ? 'is-active' : 'is-warning';
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
