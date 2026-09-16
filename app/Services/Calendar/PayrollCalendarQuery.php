<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\AttendanceRecord;
use App\Models\DailyKpiResult;
use App\Models\Payroll;
use App\Support\CalendarItem;
use App\Support\CalendarMonth;
use App\Support\CalendarViewModel;
use Illuminate\Support\Collection;

/**
 * Read-only calendar for payroll reconciliation.
 *
 * The employee_id and period boundaries are taken from the payroll model
 * itself, never from request parameters. The calendar is always read-only
 * and never triggers recalculation.
 */
final readonly class PayrollCalendarQuery
{
    public function forPayroll(Payroll $payroll): CalendarViewModel
    {
        $month = new CalendarMonth($payroll->period_start->format('Y-m'));
        $from = $payroll->period_start->toDateString();
        $to = $payroll->period_end->toDateString();
        $employeeId = $payroll->employee_id;
        $isLocked = ! $payroll->isEditable();

        $records = $this->records($employeeId, $from, $to);
        $kpiResults = $this->kpiResults($payroll->id, $from, $to);

        $itemsByDay = $this->buildItems($records, $kpiResults, $isLocked);

        return new CalendarViewModel(
            title: 'Đối soát: ' . $payroll->employee->name . ' – ' . $month->startOfMonth()->isoFormat('MMMM YYYY'),
            prevUrl: '', // Payroll calendar is fixed to the payroll period; no month nav
            nextUrl: '',
            currentUrl: '',
            weekDayLabels: $month->weekDayLabels(),
            days: $month->days(),
            itemsByDay: $itemsByDay,
            legend: $this->legend($isLocked),
            mode: 'payroll',
            canInteract: false,
        );
    }

    /**
     * @return Collection<int, AttendanceRecord>
     */
    private function records(int $employeeId, string $from, string $to): Collection
    {
        return AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->orderBy('checked_in_at')
            ->get();
    }

    /**
     * @return Collection<int, DailyKpiResult>
     */
    private function kpiResults(int $payrollId, string $from, string $to): Collection
    {
        return DailyKpiResult::query()
            ->where('payroll_id', $payrollId)
            ->whereBetween('work_date', [$from, $to])
            ->get()
            ->keyBy(fn ($r) => $r->work_date->toDateString());
    }

    /**
     * @param Collection<int, AttendanceRecord> $records
     * @param Collection<string, DailyKpiResult> $kpiResults keyed by date
     * @return array<string, Collection<int, CalendarItem>>
     */
    private function buildItems(Collection $records, Collection $kpiResults, bool $isLocked): array
    {
        /** @var array<string, Collection<int, CalendarItem>> $byDay */
        $byDay = [];

        foreach ($records as $record) {
            $date = $record->work_date->toDateString();
            $kpi = $kpiResults->get($date);

            $item = new CalendarItem(
                recordId: $isLocked ? null : $record->id,
                shiftName: $record->shift_name,
                checkedInAt: $record->checked_in_at?->format('H:i'),
                checkedOutAt: $record->checked_out_at?->format('H:i'),
                status: $record->status,
                lateMinutes: (int) $record->late_minutes,
                overtimeMinutes: (int) $record->overtime_minutes,
                overtimeStatus: $record->overtime_status,
                source: $record->source,
                isMissingCheckOut: $record->isOpenSession(),
                isSelfRecorded: $record->is_self_recorded,
                isLockedByPayroll: $isLocked,
                note: $kpi !== null ? 'KPI: ' . number_format((float) $kpi->reward_amount, 0, ',', '.') . ' đ' : $record->note,
            );
            $byDay[$date] ??= new Collection();
            $byDay[$date]->push($item);
        }

        // Add days with KPI but no attendance record (rare but possible)
        foreach ($kpiResults as $date => $kpi) {
            if (isset($byDay[$date])) {
                continue;
            }
            $item = new CalendarItem(
                shiftName: 'KPI ngày',
                note: 'KPI: ' . number_format((float) $kpi->reward_amount, 0, ',', '.') . ' đ',
                isLockedByPayroll: $isLocked,
            );
            $byDay[$date] = new Collection([$item]);
        }

        return $byDay;
    }

    /**
     * @return array<string, string>
     */
    private function legend(bool $isLocked): array
    {
        $legend = [
            'Đi làm' => 'is-success',
            'Đi trễ' => 'is-warning',
            'Nghỉ phép' => 'is-active',
            'Vắng' => 'is-danger',
        ];

        if ($isLocked) {
            $legend['Đã khóa'] = 'is-muted';
        }

        return $legend;
    }
}
