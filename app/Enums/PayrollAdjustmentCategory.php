<?php

namespace App\Enums;

enum PayrollAdjustmentCategory: string
{
    case AttendanceBonus = 'attendance_bonus';
    case DailyKpiBonus = 'daily_kpi_bonus';
    case BillKpiBonus = 'bill_kpi_bonus';
    case Allowance = 'allowance';
    case OvertimeBonus = 'overtime_bonus';
    case ManualBonus = 'manual_bonus';
    case Penalty = 'penalty';
    case MissingBillPenalty = 'missing_bill_penalty';
    case SalaryAdvance = 'salary_advance';
    case Correction = 'correction';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::AttendanceBonus => 'Thưởng chuyên cần',
            self::DailyKpiBonus => 'Thưởng KPI doanh thu ngày',
            self::BillKpiBonus => 'Thưởng KPI số bill',
            self::Allowance => 'Phụ cấp',
            self::OvertimeBonus => 'Thưởng ngoài giờ',
            self::ManualBonus => 'Thưởng khác',
            self::Penalty => 'Phạt',
            self::MissingBillPenalty => 'Phạt thiếu bill',
            self::SalaryAdvance => 'Tạm ứng',
            self::Correction => 'Điều chỉnh kỳ trước',
            self::Other => 'Khoản khác',
        };
    }

    /**
     * Categories the payroll engine owns.
     *
     * Recalculating a draft replaces exactly these rows and never touches the
     * ones a human typed in.
     */
    public function isEngineOwned(): bool
    {
        return in_array($this, [
            self::AttendanceBonus,
            self::DailyKpiBonus,
            self::BillKpiBonus,
            self::MissingBillPenalty,
        ], true);
    }

    /** @return array<int, string> */
    public static function engineOwnedValues(): array
    {
        return array_values(array_map(
            fn (self $case): string => $case->value,
            array_filter(self::cases(), fn (self $case): bool => $case->isEngineOwned()),
        ));
    }

    /** Categories a user may add by hand on a draft payroll. */
    public function isManual(): bool
    {
        return ! $this->isEngineOwned();
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

    /** The natural direction proposed in the UI for this category. */
    public function defaultDirection(): PayrollAdjustmentDirection
    {
        return match ($this) {
            self::Penalty, self::MissingBillPenalty, self::SalaryAdvance => PayrollAdjustmentDirection::Deduction,
            default => PayrollAdjustmentDirection::Earning,
        };
    }
}
