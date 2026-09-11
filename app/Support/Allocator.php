<?php

namespace App\Support;

/**
 * Splits an integer amount across keys in given proportions without losing or
 * inventing a single minor unit.
 *
 * Plain proportional maths leaves a remainder; the largest-remainder method
 * hands those leftover units to the shares that were rounded down the most, so
 * the parts always add back up to the whole.
 */
final class Allocator
{
    /**
     * @param  array<int|string, int>  $weights  proportion per key; all zero splits evenly
     * @return array<int|string, int> the same keys, with the amount distributed
     */
    public static function distribute(int $amountMinor, array $weights): array
    {
        if ($weights === []) {
            return [];
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            return self::distributeEvenly($amountMinor, array_keys($weights));
        }

        $shares = [];
        $remainders = [];
        $allocated = 0;

        foreach ($weights as $key => $weight) {
            $exact = $amountMinor * $weight;
            $share = intdiv($exact, $totalWeight);
            $shares[$key] = $share;
            $remainders[$key] = $exact - ($share * $totalWeight);
            $allocated += $share;
        }

        arsort($remainders);

        $leftover = $amountMinor - $allocated;
        $direction = $leftover < 0 ? -1 : 1;

        foreach (array_keys($remainders) as $key) {
            if ($leftover === 0) {
                break;
            }

            $shares[$key] += $direction;
            $leftover -= $direction;
        }

        return $shares;
    }

    /**
     * @param  array<int, int|string>  $keys
     * @return array<int|string, int>
     */
    private static function distributeEvenly(int $amountMinor, array $keys): array
    {
        $count = count($keys);
        $base = intdiv($amountMinor, $count);
        $leftover = $amountMinor - ($base * $count);
        $direction = $leftover < 0 ? -1 : 1;

        $shares = [];

        foreach ($keys as $key) {
            $shares[$key] = $base;

            if ($leftover !== 0) {
                $shares[$key] += $direction;
                $leftover -= $direction;
            }
        }

        return $shares;
    }
}
