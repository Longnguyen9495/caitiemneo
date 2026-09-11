<?php

namespace App\Enums;

/**
 * Outcome of the server-side location check for one clock event.
 *
 * In this MVP a failed check is refused rather than stored, so a saved row is
 * normally `Verified` or `Manual`. The failure cases still exist as values
 * because they are what the refusal is reported as, and because a row can be
 * left behind by a later change of branch coordinates or radius.
 */
enum GpsVerification: string
{
    case Verified = 'verified';
    case OutOfRange = 'out_of_range';
    case LowAccuracy = 'low_accuracy';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Verified => 'Trong vùng',
            self::OutOfRange => 'Ngoài bán kính',
            self::LowAccuracy => 'GPS không đủ chính xác',
            self::Manual => 'Nhập tay',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Verified => 'is-success',
            self::OutOfRange, self::LowAccuracy => 'is-danger',
            self::Manual => 'is-muted',
        };
    }

    /** A GPS row that did not pass needs a human to look at it. */
    public function needsReview(): bool
    {
        return in_array($this, [self::OutOfRange, self::LowAccuracy], true);
    }

    /** @return array<int, string> */
    public static function needsReviewValues(): array
    {
        return array_values(array_map(
            fn (self $case): string => $case->value,
            array_filter(self::cases(), fn (self $case): bool => $case->needsReview()),
        ));
    }
}
