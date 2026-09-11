<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Confirmed => 'Đã xác nhận',
            self::CheckedIn => 'Đã đến',
            self::Completed => 'Hoàn tất',
            self::Cancelled => 'Đã hủy',
            self::NoShow => 'Không đến',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Completed => 'is-success',
            self::Confirmed, self::CheckedIn => 'is-active',
            self::Cancelled, self::NoShow => 'is-danger',
            self::Pending => 'is-warning',
        };
    }

    /**
     * Statuses that still occupy a slot on the employee calendar.
     *
     * @return array<int, string>
     */
    public static function blockingValues(): array
    {
        return [self::Pending->value, self::Confirmed->value, self::CheckedIn->value];
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
