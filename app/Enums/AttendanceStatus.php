<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Leave = 'leave';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Đi làm',
            self::Late => 'Đi trễ',
            self::Leave => 'Nghỉ phép',
            self::Absent => 'Vắng',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Present => 'is-success',
            self::Late => 'is-warning',
            self::Leave => 'is-active',
            self::Absent => 'is-danger',
        };
    }

    /** Only worked shifts feed payroll shift pay. */
    public function isPayable(): bool
    {
        return in_array($this, [self::Present, self::Late], true);
    }

    /** @return array<int, string> */
    public static function payableValues(): array
    {
        return array_values(array_map(
            fn (self $case): string => $case->value,
            array_filter(self::cases(), fn (self $case): bool => $case->isPayable()),
        ));
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
