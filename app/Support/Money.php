<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Integer-based money helper.
 *
 * Every monetary column in this application is `decimal(14, 2)`. Floating point
 * arithmetic on those values is not safe, so all maths happens on integer minor
 * units (hundredths of a dong) and is converted back to a decimal string before
 * it touches the database.
 */
final class Money
{
    /** Number of decimal places used by every money column. */
    public const SCALE = 2;

    private const FACTOR = 100;

    /**
     * Parse any incoming money representation into integer minor units.
     *
     * Accepts decimal strings ("125000.00"), integers and user input that may
     * contain thousand separators or spaces. Values with more precision than
     * the column allows are rounded half-up.
     */
    public static function toMinor(int|float|string|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            return $value * self::FACTOR;
        }

        $normalized = self::normalize((string) $value);

        if (! preg_match('/^(-?)(\d*)(?:\.(\d+))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException("Giá trị tiền tệ không hợp lệ: {$value}");
        }

        $sign = $matches[1] === '-' ? -1 : 1;
        $whole = $matches[2] === '' ? '0' : $matches[2];
        $fraction = $matches[3] ?? '';

        $padded = str_pad(substr($fraction, 0, self::SCALE), self::SCALE, '0');
        $minor = (int) $whole * self::FACTOR + (int) $padded;

        if (strlen($fraction) > self::SCALE && (int) $fraction[self::SCALE] >= 5) {
            $minor++;
        }

        return $sign * $minor;
    }

    /** Render integer minor units as a decimal string suitable for a decimal column. */
    public static function toDecimal(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $absolute = abs($minor);

        return $sign.intdiv($absolute, self::FACTOR).'.'.str_pad((string) ($absolute % self::FACTOR), self::SCALE, '0', STR_PAD_LEFT);
    }

    /**
     * Multiply an amount by a quantity that may itself carry two decimals.
     *
     * Both operands are handled as integers, and the product is rounded half-up
     * back to minor units.
     */
    public static function multiplyByQuantity(int $minor, int|float|string|null $quantity): int
    {
        return self::divideRounded($minor * self::toMinor($quantity), self::FACTOR);
    }

    /** Apply a percentage rate (stored as `decimal(5, 2)`) to an amount. */
    public static function percentageOf(int $minor, int|float|string|null $ratePercent): int
    {
        return self::divideRounded($minor * self::toMinor($ratePercent), self::FACTOR * 100);
    }

    /**
     * Round an amount UP to the next whole step, expressed in dong.
     *
     * This is a ceiling, not a nearest-value rounding: 12.408.001 d and
     * 12.408.999 d both land on 12.409.000 d, while an amount that already sits
     * on the step is left untouched. Everything happens on integers, so no
     * `ceil()` on a float can ever drift.
     *
     * @param  int  $minor  amount in minor units
     * @param  int  $stepDong  step size in dong; zero or less disables rounding
     * @return int the rounded amount in minor units
     */
    public static function ceilToStep(int $minor, int $stepDong): int
    {
        if ($stepDong <= 0 || $minor <= 0) {
            return $minor;
        }

        $stepMinor = $stepDong * self::FACTOR;
        $remainder = $minor % $stepMinor;

        return $remainder === 0 ? $minor : $minor + ($stepMinor - $remainder);
    }

    /** Format a stored money value for display, e.g. "1.250.000 đ". */
    public static function format(int|float|string|null $value, bool $withSuffix = true): string
    {
        $minor = self::toMinor($value);
        $formatted = number_format(intdiv(abs($minor), self::FACTOR), 0, ',', '.');
        $cents = abs($minor) % self::FACTOR;

        if ($cents > 0) {
            $formatted .= ','.str_pad((string) $cents, self::SCALE, '0', STR_PAD_LEFT);
        }

        return ($minor < 0 ? '-' : '').$formatted.($withSuffix ? ' đ' : '');
    }

    /** Integer division with half-up rounding that behaves symmetrically for negatives. */
    private static function divideRounded(int $numerator, int $divisor): int
    {
        $sign = ($numerator < 0) ? -1 : 1;
        $absolute = abs($numerator);

        return $sign * intdiv($absolute * 2 + $divisor, $divisor * 2);
    }

    /**
     * Reduce user input to a plain decimal string.
     *
     * Amounts reach this class from three places: decimal columns (always
     * "1234.56"), number inputs (a dot decimal separator) and hand typed text
     * that may carry Vietnamese grouping. When both separators appear the last
     * one wins; a lone comma only counts as a decimal separator when it sits in
     * front of one or two final digits.
     */
    private static function normalize(string $value): string
    {
        $value = str_replace([' ', "\u{00A0}", '_'], '', trim($value));

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            return $lastComma > $lastDot
                ? str_replace(',', '.', str_replace('.', '', $value))
                : str_replace(',', '', $value);
        }

        if ($lastComma !== false) {
            return preg_match('/^-?\d+,\d{1,2}$/', $value) === 1
                ? str_replace(',', '.', $value)
                : str_replace(',', '', $value);
        }

        if (substr_count($value, '.') > 1) {
            return str_replace('.', '', $value);
        }

        return $value;
    }
}
