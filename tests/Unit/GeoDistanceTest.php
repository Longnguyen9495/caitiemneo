<?php

namespace Tests\Unit;

use App\Support\Coordinates;
use App\Support\GeoDistance;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GeoDistanceTest extends TestCase
{
    public function test_the_distance_from_a_point_to_itself_is_zero(): void
    {
        $point = Coordinates::make('10.7769000', '106.7009000');

        $this->assertSame(0.0, GeoDistance::haversineMeters($point, $point));
    }

    /**
     * One minute of latitude is a nautical mile by definition, so this is a
     * value that can be checked without trusting the implementation.
     */
    public function test_one_minute_of_latitude_is_one_nautical_mile(): void
    {
        $from = Coordinates::make(10.0, 106.0);
        $to = Coordinates::make(10.0 + (1 / 60), 106.0);

        $this->assertEqualsWithDelta(1852.0, GeoDistance::haversineMeters($from, $to), 3.0);
    }

    public function test_a_known_city_pair_matches_the_published_distance(): void
    {
        // Bến Thành market to Hồ Chí Minh City opera house, ~1.1 km.
        $benThanh = Coordinates::make(10.7725, 106.6980);
        $operaHouse = Coordinates::make(10.7765, 106.7030);

        $this->assertEqualsWithDelta(700.0, GeoDistance::haversineMeters($benThanh, $operaHouse), 40.0);
    }

    public function test_distance_is_symmetric(): void
    {
        $a = Coordinates::make(10.7769, 106.7009);
        $b = Coordinates::make(21.0278, 105.8342);

        $this->assertEqualsWithDelta(
            GeoDistance::haversineMeters($a, $b),
            GeoDistance::haversineMeters($b, $a),
            0.001,
        );
    }

    public function test_longitude_degrees_shrink_towards_the_pole(): void
    {
        $atEquator = GeoDistance::haversineMeters(
            Coordinates::make(0.0, 0.0),
            Coordinates::make(0.0, 1.0),
        );

        $atSixtyDegrees = GeoDistance::haversineMeters(
            Coordinates::make(60.0, 0.0),
            Coordinates::make(60.0, 1.0),
        );

        // cos(60°) is exactly 0.5, so the northern pair must be half as far.
        $this->assertEqualsWithDelta($atEquator / 2, $atSixtyDegrees, 200.0);
    }

    /**
     * The values the geofence actually turns on.
     *
     * At this latitude 0.0009° of latitude is very close to 100 m, which puts
     * one point just inside a 100 m radius and one just outside it.
     */
    public function test_points_either_side_of_a_hundred_metre_radius(): void
    {
        $branch = Coordinates::make(10.7769000, 106.7009000);

        $justInside = Coordinates::make(10.7777000, 106.7009000);
        $justOutside = Coordinates::make(10.7778500, 106.7009000);

        $this->assertLessThan(100.0, GeoDistance::haversineMeters($branch, $justInside));
        $this->assertGreaterThan(100.0, GeoDistance::haversineMeters($branch, $justOutside));
    }

    public function test_rounded_meters_returns_whole_metres(): void
    {
        $from = Coordinates::make(10.7769000, 106.7009000);
        $to = Coordinates::make(10.7777000, 106.7009000);

        $rounded = GeoDistance::roundedMeters($from, $to);

        $this->assertIsInt($rounded);
        $this->assertSame((int) round(GeoDistance::haversineMeters($from, $to)), $rounded);
    }

    public function test_coordinates_reject_an_out_of_range_latitude(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Coordinates::make(90.5, 106.0);
    }

    public function test_coordinates_reject_an_out_of_range_longitude(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Coordinates::make(10.0, 180.1);
    }

    public function test_coordinates_accept_the_exact_bounds(): void
    {
        $this->assertSame(-90.0, Coordinates::make(-90, 0)->latitude);
        $this->assertSame(180.0, Coordinates::make(0, 180)->longitude);
    }

    public function test_try_from_returns_null_for_unusable_input(): void
    {
        $this->assertNull(Coordinates::tryFrom(null, null));
        $this->assertNull(Coordinates::tryFrom('', ''));
        $this->assertNull(Coordinates::tryFrom('abc', '106.0'));
        $this->assertNull(Coordinates::tryFrom('91.0', '106.0'));
        $this->assertNotNull(Coordinates::tryFrom('10.7769000', '106.7009000'));
    }
}
