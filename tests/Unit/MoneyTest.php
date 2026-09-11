<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /** @return array<string, array{0: mixed, 1: int}> */
    public static function minorUnits(): array
    {
        return [
            'null' => [null, 0],
            'integer dong' => [125000, 12500000],
            'decimal string' => ['125000.45', 12500045],
            'trailing zero' => ['0.10', 10],
            'rounds half up' => ['0.125', 13],
            'rounds down' => ['0.124', 12],
            'negative' => ['-250.50', -25050],
            'thousand separators' => ['1,250,000', 125000000],
            'spaces' => [' 500.00 ', 50000],
        ];
    }

    #[DataProvider('minorUnits')]
    public function test_it_parses_values_into_minor_units(mixed $value, int $expected): void
    {
        $this->assertSame($expected, Money::toMinor($value));
    }

    public function test_it_renders_minor_units_back_to_a_decimal_string(): void
    {
        $this->assertSame('1250.45', Money::toDecimal(125045));
        $this->assertSame('0.05', Money::toDecimal(5));
        $this->assertSame('-12.00', Money::toDecimal(-1200));
    }

    public function test_a_round_trip_never_drifts(): void
    {
        foreach (['0.01', '0.07', '19.99', '123456789.99'] as $value) {
            $this->assertSame(
                number_format((float) $value, 2, '.', ''),
                Money::toDecimal(Money::toMinor($value)),
            );
        }
    }

    public function test_multiplying_by_a_quantity_stays_exact(): void
    {
        // 0.1 * 3 is 0.30000000000000004 in floating point.
        $this->assertSame('0.30', Money::toDecimal(Money::multiplyByQuantity(Money::toMinor('0.10'), 3)));
        $this->assertSame('300000.00', Money::toDecimal(Money::multiplyByQuantity(Money::toMinor('150000'), 2)));
        $this->assertSame('37500.00', Money::toDecimal(Money::multiplyByQuantity(Money::toMinor('15000'), '2.5')));
    }

    public function test_percentages_round_half_up(): void
    {
        $this->assertSame('30000.00', Money::toDecimal(Money::percentageOf(Money::toMinor(300000), 10)));
        $this->assertSame('0.01', Money::toDecimal(Money::percentageOf(Money::toMinor('0.10'), '5')));
        $this->assertSame('4999.95', Money::toDecimal(Money::percentageOf(Money::toMinor('33333'), '15')));
    }

    public function test_formatting_uses_vietnamese_grouping(): void
    {
        $this->assertSame('1.250.000 đ', Money::format(1250000));
        $this->assertSame('1.250.000', Money::format(1250000, false));
        $this->assertSame('-500.000 đ', Money::format(-500000));
        $this->assertSame('0 đ', Money::format(null));
        $this->assertSame('1.000,50 đ', Money::format('1000.50'));
    }

    public function test_it_understands_vietnamese_and_english_grouping(): void
    {
        $this->assertSame(125000000, Money::toMinor('1.250.000'));
        $this->assertSame(125000050, Money::toMinor('1.250.000,50'));
        $this->assertSame(125000050, Money::toMinor('1,250,000.50'));
        $this->assertSame(100050, Money::toMinor('1000,50'));
    }
}
