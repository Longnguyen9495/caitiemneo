<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Enums\AttendanceStatus;
use App\Support\CalendarDay;
use App\Support\CalendarItem;
use App\Support\CalendarViewModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class CalendarComponentRenderTest extends TestCase
{
    private function makeViewModel(array $overrides = []): CalendarViewModel
    {
        $defaults = [
            'title' => 'Tháng 9 năm 2026',
            'prevUrl' => '/prev',
            'nextUrl' => '/next',
            'currentUrl' => '/current',
            'weekDayLabels' => ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'],
            'days' => new Collection([
                new CalendarDay(CarbonImmutable::parse('2026-09-14'), true, false),
                new CalendarDay(CarbonImmutable::parse('2026-09-15'), true, false),
                new CalendarDay(CarbonImmutable::parse('2026-09-16'), true, true),
            ]),
            'itemsByDay' => [],
            'legend' => [
                'Đi làm' => 'is-success',
                'Vắng' => 'is-danger',
                'Đi trễ' => 'is-warning',
            ],
            'mode' => 'personal',
            'canInteract' => true,
        ];

        return new CalendarViewModel(...array_values(array_merge($defaults, $overrides)));
    }

    public function test_day_cell_renders_with_accessible_label(): void
    {
        $day = new CalendarDay(CarbonImmutable::parse('2026-09-16'), true, true);
        $viewModel = $this->makeViewModel();

        $html = view('components.calendar.day-cell', ['day' => $day, 'viewModel' => $viewModel])->render();

        $this->assertStringContainsString('aria-label', $html);
        $this->assertStringContainsString('16', $html);
    }

    public function test_grid_renders_toolbar_and_weekday_labels(): void
    {
        $viewModel = $this->makeViewModel();

        $html = view('components.calendar.grid', ['viewModel' => $viewModel])->render();

        $this->assertStringContainsString('Tháng 9 năm 2026', $html);
        $this->assertStringContainsString('T2', $html);
        $this->assertStringContainsString('CN', $html);
        $this->assertStringContainsString('Tháng trước', $html);
        $this->assertStringContainsString('Tháng sau', $html);
    }

    public function test_grid_renders_cells_and_legend(): void
    {
        $viewModel = $this->makeViewModel([
            'itemsByDay' => [
                '2026-09-16' => new Collection([
                    new CalendarItem(recordId: 1, status: AttendanceStatus::Present),
                ]),
            ],
        ]);

        $html = view('components.calendar.grid', ['viewModel' => $viewModel])->render();

        $this->assertStringContainsString('neo-cal__cell', $html);
        $this->assertStringContainsString('has-data', $html);
        $this->assertStringContainsString('neo-cal__legend', $html);
        $this->assertStringContainsString('Đi làm', $html);
    }

    public function test_grid_shows_warning_badge_when_day_has_issues(): void
    {
        $viewModel = $this->makeViewModel([
            'itemsByDay' => [
                '2026-09-16' => new Collection([
                    new CalendarItem(recordId: 1, status: AttendanceStatus::Late, lateMinutes: 10),
                ]),
            ],
        ]);

        $html = view('components.calendar.grid', ['viewModel' => $viewModel])->render();

        $this->assertStringContainsString('neo-cal__badge--warn', $html);
    }

    public function test_detail_panel_is_not_visible_initially(): void
    {
        $viewModel = $this->makeViewModel();

        $html = view('components.calendar.grid', ['viewModel' => $viewModel])->render();

        // Panel dùng x-if nên template không render ra DOM thật khi activeDate null
        $this->assertStringContainsString('x-if="activeDate"', $html);
        $this->assertStringContainsString('role="dialog"', $html);
    }

    public function test_legend_component_renders_items(): void
    {
        $html = view('components.calendar.legend', ['items' => [
            'Đi làm' => 'is-success',
            'Vắng' => 'is-danger',
        ]])->render();

        $this->assertStringContainsString('Đi làm', $html);
        $this->assertStringContainsString('Vắng', $html);
        $this->assertStringContainsString('neo-cal__dot--success', $html);
        $this->assertStringContainsString('neo-cal__dot--danger', $html);
    }

    public function test_today_cell_gets_is_today_class(): void
    {
        $day = new CalendarDay(CarbonImmutable::parse('2026-09-16'), true, true);
        $viewModel = $this->makeViewModel();

        $html = view('components.calendar.day-cell', ['day' => $day, 'viewModel' => $viewModel])->render();

        $this->assertStringContainsString('is-today', $html);
    }

    public function test_outside_month_cell_gets_is_outside_class(): void
    {
        $day = new CalendarDay(CarbonImmutable::parse('2026-08-31'), false, false);
        $viewModel = $this->makeViewModel();

        $html = view('components.calendar.day-cell', ['day' => $day, 'viewModel' => $viewModel])->render();

        $this->assertStringContainsString('is-outside', $html);
    }
}
