<?php

namespace App\Enums;

/**
 * What a person concluded about a flag.
 *
 * "Accepted" and "dismissed" are kept apart on purpose: both close the flag,
 * but only one of them says the concern was real. Collapsing them would lose
 * exactly the information a later investigation needs.
 */
enum RiskReviewStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Chờ xem xét',
            self::Accepted => 'Đã xác nhận là vấn đề',
            self::Dismissed => 'Đã xem, không phải vấn đề',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Accepted => 'danger',
            self::Dismissed => 'secondary',
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
