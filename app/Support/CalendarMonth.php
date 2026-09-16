<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds a month calendar grid starting Monday, with business timezone awareness.
 *
 * The grid always contains complete weeks. A month that starts on Monday and
 * has exactly 4 weeks produces 28 cells; months that spill into a sixth week
 * produce 42 cells.
 */
final readonly class CalendarMonth
{
    private CarbonImmutable $startOfMonth;

    public function __construct(?string $yearMonth = null)
    {
        $this->startOfMonth = self::resolve($yearMonth);
    }

    /**
     * Parse the requested month safely. Invalid input falls back to now.
     */
    private static function resolve(?string $yearMonth): CarbonImmutable
    {
        $now = CarbonImmutable::now()->startOfMonth();

        if ($yearMonth === null || $yearMonth === '') {
            return $now;
        }

        if (! preg_match('/^(\d{4})-(\d{2})$/', $yearMonth, $matches)) {
            return $now;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];

        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            return $now;
        }

        try {
            return CarbonImmutable::create($year, $month, 1)->startOfMonth();
        } catch (\Exception) {
            return $now;
        }
    }

    public function yearMonth(): string
    {
        return $this->startOfMonth->format('Y-m');
    }

    public function startOfMonth(): CarbonImmutable
    {
        return $this->startOfMonth;
    }

    public function endOfMonth(): CarbonImmutable
    {
        return $this->startOfMonth->endOfMonth();
    }

    /**
     * @return Collection<int, CalendarDay>
     */
    public function days(): Collection
    {
        $today = CarbonImmutable::now()->startOfDay();
        $startOfGrid = $this->startOfMonth->startOfWeek(CarbonImmutable::MONDAY);
        $endOfGrid = $this->startOfMonth->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);

        $days = new Collection();
        $cursor = $startOfGrid->copy();

        while ($cursor->lte($endOfGrid)) {
            $days->push(new CalendarDay(
                date: $cursor,
                isInMonth: $cursor->isSameMonth($this->startOfMonth),
                isToday: $cursor->equalTo($today),
            ));
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return array<int, string>
     */
    public function weekDayLabels(): array
    {
        return ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
    }

    public function prevMonthUrl(string $baseUrl): string
    {
        return $this->monthUrl($baseUrl, $this->startOfMonth->subMonthNoOverflow());
    }

    public function nextMonthUrl(string $baseUrl): string
    {
        return $this->monthUrl($baseUrl, $this->startOfMonth->addMonthNoOverflow());
    }

    public function currentMonthUrl(string $baseUrl): string
    {
        return $this->monthUrl($baseUrl, CarbonImmutable::now()->startOfMonth());
    }

    private function monthUrl(string $baseUrl, CarbonImmutable $month): string
    {
        $parsed = parse_url($baseUrl);
        $query = [];

        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        $query['month'] = $month->format('Y-m');

        $path = $parsed['path'] ?? '/';
        $qs = http_build_query($query);

        return $path . '?' . $qs;
    }
}
