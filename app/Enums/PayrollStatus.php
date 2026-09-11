<?php

namespace App\Enums;

enum PayrollStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Finalized => 'Đã chốt',
            self::Paid => 'Đã trả',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'is-warning',
            self::Finalized => 'is-active',
            self::Paid => 'is-success',
            self::Cancelled => 'is-danger',
        };
    }

    /** Draft payrolls are the only ones that may be recalculated or edited. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
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
