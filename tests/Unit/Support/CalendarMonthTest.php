<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CalendarDay;
use App\Support\CalendarMonth;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * @covers \App\Support\CalendarMonth
 * @covers \App\Support\CalendarDay
 */
class CalendarMonthTest extends TestCase
{
    public function test_it_builds_a_31_day_month(): void
    {
        // March 2025 starts on Saturday; grid spans complete weeks
        $month = new CalendarMonth('2025-03');
        $days = $month->days();

        $this->assertCount(42, $days);
        $this->assertTrue($days->first()->date->isMonday());
        $this->assertSame('2025-02-24', $days->first()->isoDate);
        $this->assertFalse($days->first()->isInMonth);
        $this->assertSame('2025-04-06', $days->last()->isoDate);
        $this->assertFalse($days->last()->isInMonth);

        $inMonth = $days->filter(fn (CalendarDay $d): bool => $d->isInMonth);
        $this->assertCount(31, $inMonth);
    }

    public function test_it_builds_a_28_day_month(): void
    {
        // February 2026 starts on Sunday; grid spans 5 weeks
        $month = new CalendarMonth('2026-02');
        $days = $month->days();

        $this->assertCount(35, $days);
        $this->assertTrue($days->first()->date->isMonday());
        $this->assertSame('2026-01-26', $days->first()->isoDate);
        $this->assertSame('2026-03-01', $days->last()->isoDate);

        $inMonth = $days->filter(fn (CalendarDay $d): bool => $d->isInMonth);
        $this->assertCount(28, $inMonth);
    }

    public function test_it_builds_a_29_day_leap_month(): void
    {
        // February 2024 is a leap year, starts on Thursday
        $month = new CalendarMonth('2024-02');
        $days = $month->days();

        $this->assertCount(35, $days);
        $this->assertSame('2024-01-29', $days->first()->isoDate); // Monday before Feb 1
        $this->assertSame('2024-03-03', $days->last()->isoDate);  // Sunday after Feb 29
    }

    public function test_it_builds_a_30_day_month_starting_on_sunday(): void
    {
        // April 2029 starts on Sunday (30 days) -> needs 6 weeks (42 cells)
        $month = new CalendarMonth('2029-04');
        $days = $month->days();

        $this->assertCount(42, $days);
        $this->assertTrue($days->first()->date->isMonday());
        $this->assertSame('2029-03-26', $days->first()->isoDate);
        $this->assertSame('2029-05-06', $days->last()->isoDate);
    }

    public function test_it_handles_december_to_january_transition(): void
    {
        $month = new CalendarMonth('2025-12');
        $days = $month->days();

        $this->assertTrue($days->contains(fn (CalendarDay $d): bool => $d->isoDate === '2025-12-01'));
        $this->assertTrue($days->contains(fn (CalendarDay $d): bool => $d->isoDate === '2026-01-04'));
    }

    public function test_it_marks_days_outside_month_correctly(): void
    {
        $month = new CalendarMonth('2025-03');
        $days = $month->days();

        // First day is Feb 24, outside March
        $this->assertFalse($days->first()->isInMonth);
        $marchDays = $days->filter(fn (CalendarDay $d): bool => $d->isInMonth);
        $this->assertCount(31, $marchDays);
    }

    public function test_it_marks_today(): void
    {
        CarbonImmutable::setTestNow('2025-03-15');
        $month = new CalendarMonth('2025-03');
        $days = $month->days();

        $today = $days->first(fn (CalendarDay $d): bool => $d->isoDate === '2025-03-15');
        $this->assertNotNull($today);
        $this->assertTrue($today->isToday);

        $other = $days->first(fn (CalendarDay $d): bool => $d->isoDate === '2025-03-14');
        $this->assertNotNull($other);
        $this->assertFalse($other->isToday);

        CarbonImmutable::setTestNow();
    }

    public function test_month_starting_monday_has_first_cell_in_month(): void
    {
        // September 2025 starts on Monday (30 days) -> grid starts right on 1st
        $month = new CalendarMonth('2025-09');
        $days = $month->days();

        $this->assertTrue($days->first()->isInMonth);
        $this->assertSame('2025-09-01', $days->first()->isoDate);
    }

    public function test_null_month_defaults_to_current(): void
    {
        CarbonImmutable::setTestNow('2025-06-15');
        $month = new CalendarMonth(null);

        $this->assertSame('2025-06', $month->yearMonth());
        CarbonImmutable::setTestNow();
    }

    #[DataProvider('invalidMonthProvider')]
    public function test_invalid_month_falls_back_to_current(string $invalid): void
    {
        CarbonImmutable::setTestNow('2025-06-15');
        $month = new CalendarMonth($invalid);

        $this->assertSame('2025-06', $month->yearMonth());
        CarbonImmutable::setTestNow();
    }

    public static function invalidMonthProvider(): array
    {
        return [
            'empty string' => [''],
            'garbage' => ['not-a-month'],
            'wrong format' => ['2025/03'],
            'month zero' => ['2025-00'],
            'month thirteen' => ['2025-13'],
            'year way too far' => ['3000-01'],
        ];
    }

    public function test_start_and_end_of_month(): void
    {
        $month = new CalendarMonth('2025-03');

        $this->assertSame('2025-03-01', $month->startOfMonth()->toDateString());
        $this->assertSame('2025-03-31', $month->endOfMonth()->toDateString());
    }

    public function test_prev_and_next_urls_preserve_base(): void
    {
        $month = new CalendarMonth('2025-03');

        $this->assertStringContainsString('month=2025-02', $month->prevMonthUrl('/attendance'));
        $this->assertStringContainsString('month=2025-04', $month->nextMonthUrl('/attendance'));
    }

    public function test_prev_and_next_urls_include_existing_query(): void
    {
        $month = new CalendarMonth('2025-03');
        $url = $month->prevMonthUrl('/attendance?employee=5');

        $this->assertStringContainsString('employee=5', $url);
        $this->assertStringContainsString('month=2025-02', $url);
    }

    public function test_week_day_labels_start_monday(): void
    {
        $month = new CalendarMonth('2025-03');
        $labels = $month->weekDayLabels();

        $this->assertSame(['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'], $labels);
    }

    public function test_each_day_has_iso_date_and_day_of_month(): void
    {
        $month = new CalendarMonth('2025-03');
        $days = $month->days();

        foreach ($days as $day) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $day->isoDate);
            $this->assertGreaterThanOrEqual(1, $day->dayOfMonth);
            $this->assertLessThanOrEqual(31, $day->dayOfMonth);
        }
    }
}
