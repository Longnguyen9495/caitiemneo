<?php

namespace App\Support;

/**
 * Great-circle distance between two points.
 *
 * The haversine formula is used rather than the equirectangular shortcut
 * because it stays accurate at any latitude, and rather than Vincenty because
 * at the scale of a shop's geofence the ellipsoidal correction is far below the
 * accuracy a phone reports anyway.
 *
 * The distance the browser sends is never trusted: it is always recomputed
 * here from the coordinates.
 */
final class GeoDistance
{
    /**
     * Mean earth radius in metres (IUGG).
     *
     * Using the mean radius keeps the error under about 0.3% worldwide, which
     * is an order of magnitude smaller than a phone's own GPS accuracy.
     */
    public const EARTH_RADIUS_METERS = 6_371_008.8;

    public static function haversineMeters(Coordinates $from, Coordinates $to): float
    {
        $latFrom = deg2rad($from->latitude);
        $latTo = deg2rad($to->latitude);
        $deltaLat = $latTo - $latFrom;
        $deltaLng = deg2rad($to->longitude - $from->longitude);

        $a = (sin($deltaLat / 2) ** 2)
            + (cos($latFrom) * cos($latTo) * (sin($deltaLng / 2) ** 2));

        // `min(1.0, ...)` guards asin() against a value drifting just past 1
        // through floating point error when the two points are identical.
        return 2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($a)));
    }

    /** Rounded to whole metres, which is all the evidence columns store. */
    public static function roundedMeters(Coordinates $from, Coordinates $to): int
    {
        return (int) round(self::haversineMeters($from, $to));
    }
}
