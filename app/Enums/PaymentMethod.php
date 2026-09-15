<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Card = 'card';
    case Ewallet = 'ewallet';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tiền mặt',
            self::Transfer => 'Chuyển khoản',
            self::Card => 'Quẹt thẻ',
            self::Ewallet => 'Ví điện tử',
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

    /** Payment methods accepted when settling a customer invoice. */
    public static function invoiceOptions(): array
    {
        return [
            self::Cash->value => self::Cash->label(),
            self::Transfer->value => self::Transfer->label(),
        ];
    }
}
