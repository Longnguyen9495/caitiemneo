<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * A validated WGS-84 point.
 *
 * Coordinates are stored as `decimal(10, 7)` so they round-trip exactly; the
 * float conversion here exists only because the distance formula is
 * trigonometric, and it happens after the value has been range-checked.
 */
final readonly class Coordinates
{
    private function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    /** @throws InvalidArgumentException */
    public static function make(int|float|string $latitude, int|float|string $longitude): self
    {
        $lat = self::toFloat($latitude, 'vĩ độ');
        $lng = self::toFloat($longitude, 'kinh độ');

        if ($lat < -90.0 || $lat > 90.0) {
            throw new InvalidArgumentException('Vĩ độ phải nằm trong khoảng -90 đến 90.');
        }

        if ($lng < -180.0 || $lng > 180.0) {
            throw new InvalidArgumentException('Kinh độ phải nằm trong khoảng -180 đến 180.');
        }

        return new self($lat, $lng);
    }

    /** The same as {@see make()} but returns null instead of throwing. */
    public static function tryFrom(int|float|string|null $latitude, int|float|string|null $longitude): ?self
    {
        if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            return null;
        }

        try {
            return self::make($latitude, $longitude);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** Metres to another point, along the surface of the earth. */
    public function distanceTo(self $other): float
    {
        return GeoDistance::haversineMeters($this, $other);
    }

    private static function toFloat(int|float|string $value, string $label): float
    {
        if (is_string($value) && ! is_numeric(trim($value))) {
            throw new InvalidArgumentException(sprintf('Giá trị %s không hợp lệ.', $label));
        }

        return (float) $value;
    }
}
