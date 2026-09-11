<?php

namespace App\Enums;

enum CashTransactionCategory: string
{
    case ServiceRevenue = 'service_revenue';
    case OtherIncome = 'other_income';
    case Refund = 'refund';
    case Inventory = 'inventory';
    case Payroll = 'payroll';
    case Rent = 'rent';
    case Utilities = 'utilities';
    case Marketing = 'marketing';
    case OtherExpense = 'other_expense';

    public function label(): string
    {
        return match ($this) {
            self::ServiceRevenue => 'Doanh thu dịch vụ',
            self::OtherIncome => 'Thu khác',
            self::Refund => 'Hoàn tiền hóa đơn',
            self::Inventory => 'Nhập kho vật tư',
            self::Payroll => 'Lương nhân viên',
            self::Rent => 'Mặt bằng',
            self::Utilities => 'Điện nước và vận hành',
            self::Marketing => 'Marketing',
            self::OtherExpense => 'Chi khác',
        };
    }

    /** Categories a user may pick when recording a transaction by hand. */
    public function isManual(): bool
    {
        return ! in_array($this, [self::ServiceRevenue, self::Refund, self::Payroll], true);
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

    /** @return array<string, string> */
    public static function manualOptions(): array
    {
        return array_reduce(
            array_filter(self::cases(), fn (self $case): bool => $case->isManual()),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
