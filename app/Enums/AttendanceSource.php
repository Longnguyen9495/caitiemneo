<?php

namespace App\Enums;

/**
 * How an attendance row came into existence.
 *
 * The distinction matters for trust: a GPS row carries machine-collected
 * evidence, a manual row carries a manager's name and a reason.
 */
enum AttendanceSource: string
{
    case Gps = 'gps';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Gps => 'Tự chấm bằng GPS',
            self::Manual => 'Quản lý nhập tay',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Gps => 'is-success',
            self::Manual => 'is-muted',
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
