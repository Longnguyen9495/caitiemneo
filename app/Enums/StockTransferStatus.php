<?php

namespace App\Enums;

enum StockTransferStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Completed => 'Đã chuyển',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'is-warning',
            self::Completed => 'is-success',
            self::Cancelled => 'is-danger',
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
