<?php

namespace App\Enums;

/**
 * How hard a flag argues for someone to look.
 *
 * Deliberately only three levels. More would invite arguing about the boundary
 * instead of reading the queue, and a queue nobody reads protects nothing.
 */
enum RiskSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Thấp',
            self::Medium => 'Trung bình',
            self::High => 'Cao',
        };
    }

    /** Bootstrap tone used by the queue. */
    public function tone(): string
    {
        return match ($this) {
            self::Low => 'secondary',
            self::Medium => 'warning',
            self::High => 'danger',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
        };
    }
}
