<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AttendanceStatus;
use Illuminate\Support\Collection;

/**
 * Read-only view model for a calendar page.
 *
 * Carries the grid, navigation URLs, and per-day data already grouped by the
 * service layer so the Blade never queries the database.
 */
final readonly class CalendarViewModel
{
    /**
     * @param  string  $title  e.g. "Tháng 3 năm 2025"
     * @param  string  $prevUrl  URL for previous month keeping existing query params
     * @param  string  $nextUrl  URL for next month keeping existing query params
     * @param  string  $currentUrl  URL for current month
     * @param  array<int, string>  $weekDayLabels
     * @param  Collection<int, CalendarDay>  $days
     * @param  array<string, Collection<int, CalendarItem>>  $itemsByDay  isoDate => items
     * @param  array<string, string>  $legend  status label => tone class
     * @param  string  $mode  'personal'|'admin-overview'|'admin-employee'|'payroll'
     * @param  bool  $canInteract  whether any create/edit links are present
     * @param  bool  $canOpenDetails  whether the day detail panel can be opened
     */
    public function __construct(
        public string $title,
        public string $prevUrl,
        public string $nextUrl,
        public string $currentUrl,
        public array $weekDayLabels,
        public Collection $days,
        public array $itemsByDay,
        public array $legend,
        public string $mode,
        public bool $canInteract = false,
        public bool $canOpenDetails = true,
    ) {}

    /**
     * Items for a specific day, or an empty collection.
     *
     * @return Collection<int, CalendarItem>
     */
    public function itemsForDay(string $isoDate): Collection
    {
        $value = $this->itemsByDay[$isoDate] ?? null;

        if ($value === null) {
            return new Collection;
        }

        return $value instanceof Collection ? $value : new Collection($value);
    }

    /**
     * The most serious tone for a day cell, based on item priorities.
     */
    public function dayTone(string $isoDate): string
    {
        $items = $this->itemsForDay($isoDate);

        if ($items->isEmpty()) {
            return 'is-empty';
        }

        $best = $items->sortBy(fn (CalendarItem $item): int => $item->tonePriority())->first();

        return match ($best->status) {
            null => $best->shiftName !== null ? 'is-planned' : 'is-empty',
            default => $best->status->tone(),
        };
    }

    /**
     * Count of records in a day.
     */
    public function dayRecordCount(string $isoDate): int
    {
        return $this->itemsForDay($isoDate)->filter(fn (CalendarItem $i): bool => $i->recordId !== null)->count();
    }

    /**
     * Count of warnings in a day.
     */
    public function dayWarningCount(string $isoDate): int
    {
        return $this->itemsForDay($isoDate)->filter(fn (CalendarItem $i): bool => $i->isMissingCheckOut
            || $i->lateMinutes > 0
            || $i->gpsNeedsReview
            || $i->overtimeNeedsReview()
            || $i->status === AttendanceStatus::Absent
        )->count();
    }
}
