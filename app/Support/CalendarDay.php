<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * A single cell in a calendar grid.
 *
 * @property-read string $isoDate Y-m-d
 * @property-read int $dayOfMonth 1-31
 * @property-read bool $isInMonth Whether this day belongs to the viewed month
 * @property-read bool $isToday Whether this day is today in business timezone
 * @property-read string $accessibleLabel Full label for screen readers (e.g. "Thứ Ba, 15 tháng 3")
 * @property-read CarbonImmutable $date The underlying Carbon instance
 */
final readonly class CalendarDay
{
    public function __construct(
        public CarbonImmutable $date,
        public bool $isInMonth,
        public bool $isToday,
    ) {}

    public function __get(string $name): mixed
    {
        return match ($name) {
            'isoDate' => $this->date->toDateString(),
            'dayOfMonth' => (int) $this->date->format('j'),
            'accessibleLabel' => $this->date->isoFormat('dddd, D MMMM'),
            default => throw new \InvalidArgumentException("Unknown property: {$name}"),
        };
    }
}
