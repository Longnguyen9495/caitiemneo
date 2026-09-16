<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\AttendanceStatus;
use App\Support\CalendarDay;
use App\Support\CalendarItem;
use App\Support\CalendarViewModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class CalendarViewModelTest extends TestCase
{
    private function makeModel(array $overrides = []): CalendarViewModel
    {
        $defaults = [
            'title' => 'Tháng 9 năm 2026',
            'prevUrl' => '/prev',
            'nextUrl' => '/next',
            'currentUrl' => '/current',
            'weekDayLabels' => ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'],
            'days' => new Collection([
                new CalendarDay(CarbonImmutable::parse('2026-09-16'), true, true),
                new CalendarDay(CarbonImmutable::parse('2026-09-17'), true, false),
            ]),
            'itemsByDay' => [],
            'legend' => [],
            'mode' => 'personal',
            'canInteract' => true,
        ];

        return new CalendarViewModel(...array_values(array_merge($defaults, $overrides)));
    }

    public function test_items_for_day_returns_empty_collection_when_no_data(): void
    {
        $model = $this->makeModel();

        $this->assertTrue($model->itemsForDay('2026-09-16')->isEmpty());
    }

    public function test_items_for_day_returns_items_when_present(): void
    {
        $item = new CalendarItem(recordId: 1, status: AttendanceStatus::Present);
        $model = $this->makeModel(['itemsByDay' => [
            '2026-09-16' => new Collection([$item]),
        ]]);

        $items = $model->itemsForDay('2026-09-16');

        $this->assertCount(1, $items);
        $this->assertSame(1, $items->first()->recordId);
    }

    public function test_day_tone_is_empty_when_no_items(): void
    {
        $model = $this->makeModel();

        $this->assertSame('is-empty', $model->dayTone('2026-09-16'));
    }

    public function test_day_tone_uses_most_severe_status(): void
    {
        $items = new Collection([
            new CalendarItem(recordId: 1, status: AttendanceStatus::Present),
            new CalendarItem(recordId: 2, status: AttendanceStatus::Absent),
        ]);
        $model = $this->makeModel(['itemsByDay' => ['2026-09-16' => $items]]);

        $this->assertSame('is-danger', $model->dayTone('2026-09-16'));
    }

    public function test_day_tone_planned_for_future_shift(): void
    {
        $items = new Collection([
            new CalendarItem(shiftName: 'Ca sáng'),
        ]);
        $model = $this->makeModel(['itemsByDay' => ['2026-09-16' => $items]]);

        $this->assertSame('is-planned', $model->dayTone('2026-09-16'));
    }

    public function test_day_record_count_counts_only_items_with_record_id(): void
    {
        $items = new Collection([
            new CalendarItem(recordId: 1),
            new CalendarItem(shiftName: 'Ca tối'),
            new CalendarItem(recordId: 3),
        ]);
        $model = $this->makeModel(['itemsByDay' => ['2026-09-16' => $items]]);

        $this->assertSame(2, $model->dayRecordCount('2026-09-16'));
    }

    public function test_day_warning_count_detects_missing_checkout(): void
    {
        $items = new Collection([
            new CalendarItem(recordId: 1, status: AttendanceStatus::Present, isMissingCheckOut: true),
        ]);
        $model = $this->makeModel(['itemsByDay' => ['2026-09-16' => $items]]);

        $this->assertSame(1, $model->dayWarningCount('2026-09-16'));
    }

    public function test_day_warning_count_detects_late(): void
    {
        $items = new Collection([
            new CalendarItem(recordId: 1, status: AttendanceStatus::Late, lateMinutes: 15),
        ]);
        $model = $this->makeModel(['itemsByDay' => ['2026-09-16' => $items]]);

        $this->assertSame(1, $model->dayWarningCount('2026-09-16'));
    }

    public function test_day_warning_count_detects_absent(): void
    {
        $items = new Collection([
            new CalendarItem(recordId: 1, status: AttendanceStatus::Absent),
        ]);
        $model = $this->makeModel(['itemsByDay' => ['2026-09-16' => $items]]);

        $this->assertSame(1, $model->dayWarningCount('2026-09-16'));
    }

    public function test_day_warning_count_is_zero_for_clean_present(): void
    {
        $items = new Collection([
            new CalendarItem(recordId: 1, status: AttendanceStatus::Present),
        ]);
        $model = $this->makeModel(['itemsByDay' => ['2026-09-16' => $items]]);

        $this->assertSame(0, $model->dayWarningCount('2026-09-16'));
    }
}
