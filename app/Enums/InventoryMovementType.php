<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Nhập kho',
            self::Out => 'Xuất kho',
            self::Adjustment => 'Điều chỉnh',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::In => 'is-success',
            self::Out => 'is-danger',
            self::Adjustment => 'is-warning',
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
