<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\DailyKpiResult;
use App\Models\Payroll;
use App\Support\BranchContext;
use App\Support\CalendarDay;
use App\Support\CalendarItem;
use App\Support\CalendarViewModel;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds a read-only calendar for payroll reconciliation.
 *
 * - Range is driven by payroll period_start / period_end, not a free month.
 * - Never expands to full calendar months; grid starts on Monday of the
 *   week containing period_start and ends on Sunday of the week containing
 *   period_end.
 * - Attendance is scoped to the payroll employee and period.
 * - Daily KPI results are loaded from the already-stored snapshot.
 * - No edit links are ever generated; the calendar is strictly explanatory.
 */
final readonly class PayrollCalendarQuery
{
    public function __construct(private BranchContext $branchContext) {}

    public function forPayroll(Payroll $payroll, string $baseUrl = ''): CalendarViewModel
    {
        $from = CarbonImmutable::parse($payroll->period_start)->startOfDay();
        $to = CarbonImmutable::parse($payroll->period_end)->endOfDay();

        $records = $this->records($payroll, $from->toDateString(), $to->toDateString());
        $kpiResults = $this->kpiResults($payroll, $from->toDateString(), $to->toDateString());

        $items = $this->buildItems($records, $kpiResults, ! $payroll->isEditable());

        $days = $this->buildDays($from, $to);

        $title = sprintf(
            'Kỳ lương %s — %s',
            $payroll->period_start->format('d/m/Y'),
            $payroll->period_end->format('d/m/Y'),
        );

        return new CalendarViewModel(
            title: $title,
            prevUrl: '',
            nextUrl: '',
            currentUrl: $baseUrl,
            weekDayLabels: ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'],
            days: $days,
            itemsByDay: $items,
            legend: $this->legend(),
            mode: 'payroll',
            canInteract: false,
            canOpenDetails: true,
        );
    }

    /** @return Collection<int, AttendanceRecord> */
    private function records(Payroll $payroll, string $from, string $to): Collection
    {
        return AttendanceRecord::query()
            ->where('employee_id', $payroll->employee_id)
            ->whereBetween('work_date', [$from, $to])
            ->with(['shiftAssignment.workShift'])
            ->orderBy('work_date')
            ->get();
    }

    /** @return Collection<string, Collection<int, DailyKpiResult>> grouped by Y-m-d */
    private function kpiResults(Payroll $payroll, string $from, string $to): Collection
    {
        return DailyKpiResult::query()
            ->where('payroll_id', $payroll->id)
            ->whereBetween('work_date', [$from, $to])
            ->with(['branch', 'tier'])
            ->orderBy('work_date')
            ->get()
            ->groupBy(fn (DailyKpiResult $r): string => $r->work_date->toDateString());
    }

    /**
     * @param  Collection<int, AttendanceRecord>  $records
     * @param  Collection<string, Collection<int, DailyKpiResult>>  $kpiByDate
     * @return array<string, list<CalendarItem>>
     */
    private function buildItems(Collection $records, Collection $kpiByDate, bool $isLocked): array
    {
        $items = [];

        foreach ($records as $record) {
            $iso = $record->work_date->toDateString();

            $kpis = $kpiByDate->get($iso);
            $note = $this->kpiNote($kpis);

            $items[$iso][] = new CalendarItem(
                recordId: $record->id,
                shiftName: $record->shift_name ?? $record->shiftAssignment?->workShift?->name ?? 'Ca làm',
                status: $record->status,
                checkedInAt: $record->checked_in_at?->format('H:i'),
                checkedOutAt: $record->checked_out_at?->format('H:i'),
                lateMinutes: (int) $record->late_minutes,
                overtimeMinutes: (int) ($record->approved_overtime_minutes ?? 0),
                overtimeStatus: $record->overtime_status,
                source: $record->source,
                isMissingCheckOut: $record->isOpenSession(),
                isLockedByPayroll: $isLocked,
                note: $note,
            );
        }

        // Days with KPI but no attendance record
        foreach ($kpiByDate as $iso => $kpis) {
            if (! isset($items[$iso])) {
                $items[$iso][] = new CalendarItem(
                    recordId: null,
                    shiftName: 'Không có ca',
                    status: null,
                    isLockedByPayroll: $isLocked,
                    note: $this->kpiNote($kpis),
                );
            }
        }

        return $items;
    }

    /**
     * @param  Collection<int, DailyKpiResult>|null  $kpis
     */
    private function kpiNote(?Collection $kpis): ?string
    {
        if ($kpis === null || $kpis->isEmpty()) {
            return null;
        }

        $notes = $kpis->map(function (DailyKpiResult $kpi): string {
            $note = 'KPI '.Money::format($kpi->reward_amount);
            if ($kpi->branch?->code) {
                $note .= ' tại '.$kpi->branch->code;
            }

            return $note;
        });

        return $notes->implode('; ');
    }

    /** @return Collection<int, CalendarDay> */
    private function buildDays(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $today = CarbonImmutable::now()->startOfDay();
        $startOfGrid = $from->startOfWeek(CarbonImmutable::MONDAY);
        $endOfGrid = $to->endOfWeek(CarbonImmutable::SUNDAY);

        $days = new Collection;
        $cursor = $startOfGrid->copy();

        while ($cursor->lte($endOfGrid)) {
            $days->push(new CalendarDay(
                date: $cursor,
                isInMonth: $cursor->between($from, $to),
                isToday: $cursor->equalTo($today),
            ));
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /** @return array<string, string> */
    private function legend(): array
    {
        return [
            AttendanceStatus::Present->label() => AttendanceStatus::Present->tone(),
            AttendanceStatus::Late->label() => AttendanceStatus::Late->tone(),
            AttendanceStatus::Absent->label() => AttendanceStatus::Absent->tone(),
            'Nghỉ phép' => AttendanceStatus::Leave->tone(),
            'KPI ngày' => 'is-success',
        ];
    }
}
