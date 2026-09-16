<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\AttendanceRecord;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\CalendarItem;
use App\Support\CalendarMonth;
use App\Support\CalendarViewModel;
use Illuminate\Support\Collection;

/**
 * Builds calendar view models for the admin attendance page.
 *
 * Supports two modes:
 * - overview: aggregate counts per day for all employees in branch scope.
 * - employee: full detail for one validated employee in scope.
 */
final readonly class AdminCalendarQuery
{
    public function __construct(private BranchContext $branchContext) {}

    /**
     * Overview mode: count people/records and warnings per day.
     *
     * @param array<int, int> $employeeIds Optional filter to specific employees
     */
    public function overview(?string $yearMonth = null, array $employeeIds = [], string $baseUrl = '/admin/attendance'): CalendarViewModel
    {
        $month = new CalendarMonth($yearMonth);
        $from = $month->startOfMonth()->toDateString();
        $to = $month->endOfMonth()->toDateString();

        $branchIds = $this->branchContext->scopeIds() ?: [0];

        $records = AttendanceRecord::query()
            ->with('employee')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('work_date', [$from, $to])
            ->when($employeeIds !== [], fn ($q) => $q->whereIn('employee_id', $employeeIds))
            ->orderBy('work_date')
            ->get();

        $itemsByDay = $this->buildOverviewItems($records);

        return new CalendarViewModel(
            title: $month->startOfMonth()->isoFormat('MMMM YYYY'),
            prevUrl: $month->prevMonthUrl($baseUrl),
            nextUrl: $month->nextMonthUrl($baseUrl),
            currentUrl: $month->currentMonthUrl($baseUrl),
            weekDayLabels: $month->weekDayLabels(),
            days: $month->days(),
            itemsByDay: $itemsByDay,
            legend: $this->legend(),
            mode: 'admin-overview',
            canInteract: true,
        );
    }

    /**
     * Employee mode: detailed items for one employee.
     */
    public function forEmployee(User $employee, ?string $yearMonth = null, string $baseUrl = '/admin/attendance'): CalendarViewModel
    {
        $month = new CalendarMonth($yearMonth);
        $from = $month->startOfMonth()->toDateString();
        $to = $month->endOfMonth()->toDateString();

        $records = AttendanceRecord::query()
            ->where('employee_id', $employee->getKey())
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->orderBy('checked_in_at')
            ->get();

        $assignments = ShiftAssignment::query()
            ->where('employee_id', $employee->getKey())
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->get();

        $itemsByDay = $this->buildEmployeeItems($records, $assignments);

        return new CalendarViewModel(
            title: $month->startOfMonth()->isoFormat('MMMM YYYY'),
            prevUrl: $month->prevMonthUrl($baseUrl . '?employee_id=' . $employee->getKey()),
            nextUrl: $month->nextMonthUrl($baseUrl . '?employee_id=' . $employee->getKey()),
            currentUrl: $month->currentMonthUrl($baseUrl . '?employee_id=' . $employee->getKey()),
            weekDayLabels: $month->weekDayLabels(),
            days: $month->days(),
            itemsByDay: $itemsByDay,
            legend: $this->legend(),
            mode: 'admin-employee',
            canInteract: true,
        );
    }

    /**
     * @param Collection<int, AttendanceRecord> $records
     * @return array<string, Collection<int, CalendarItem>>
     */
    private function buildOverviewItems(Collection $records): array
    {
        /** @var array<string, Collection<int, CalendarItem>> $byDay */
        $byDay = [];

        $grouped = $records->groupBy(fn ($r) => $r->work_date->toDateString());

        foreach ($grouped as $date => $dayRecords) {
            $present = $dayRecords->filter(fn ($r) => in_array($r->status->value, ['present', 'late'], true))->count();
            $absent = $dayRecords->filter(fn ($r) => $r->status->value === 'absent')->count();
            $hasWarning = $dayRecords->contains(fn ($r) =>
                $r->isOpenSession()
                || $r->late_minutes > 0
                || $this->gpsNeedsReview($r)
                || $r->overtime_status->value === 'pending'
            );

            $presentCount = $present > 0 ? "{$present} đi làm" : '';
            $absentCount = $absent > 0 ? "{$absent} vắng" : '';
            $label = implode(' · ', array_filter([$presentCount, $absentCount])) ?: 'Không có ca';

            // Tone dựa trên trạng thái nghiêm trọng nhất trong ngày
            $status = $hasWarning ? \App\Enums\AttendanceStatus::Late : ($absent > 0 ? \App\Enums\AttendanceStatus::Absent : \App\Enums\AttendanceStatus::Present);

            $item = new CalendarItem(
                recordId: null,
                shiftName: $label,
                status: $status,
                isMissingCheckOut: $dayRecords->contains(fn ($r) => $r->isOpenSession()),
            );

            $byDay[$date] = new Collection([$item]);
        }

        return $byDay;
    }

    /**
     * @param Collection<int, AttendanceRecord> $records
     * @param Collection<int, ShiftAssignment> $assignments
     * @return array<string, Collection<int, CalendarItem>>
     */
    private function buildEmployeeItems(Collection $records, Collection $assignments): array
    {
        /** @var array<string, Collection<int, CalendarItem>> $byDay */
        $byDay = [];

        foreach ($records as $record) {
            $date = $record->work_date->toDateString();
            $item = new CalendarItem(
                recordId: $record->id,
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
                gpsNeedsReview: $this->gpsNeedsReview($record),
                viewUrl: route('admin.attendance.edit', $record),
                note: $record->note,
            );
            $byDay[$date] ??= new Collection();
            $byDay[$date]->push($item);
        }

        $recordedAssignmentIds = $records->pluck('shift_assignment_id')->filter()->unique()->all();

        foreach ($assignments as $assignment) {
            if ($assignment->attendanceRecord !== null || in_array($assignment->id, $recordedAssignmentIds, true)) {
                continue;
            }

            $date = $assignment->work_date->toDateString();
            $item = new CalendarItem(
                shiftName: $assignment->shift_name,
                plannedStartAt: $assignment->planned_start_at?->format('H:i'),
                plannedEndAt: $assignment->planned_end_at?->format('H:i'),
            );
            $byDay[$date] ??= new Collection();
            $byDay[$date]->push($item);
        }

        return $byDay;
    }

    private function gpsNeedsReview(AttendanceRecord $record): bool
    {
        if ($record->source->value !== 'gps') {
            return false;
        }

        $checkInNeedsReview = $record->check_in_verification !== null
            && in_array($record->check_in_verification->value, ['needs_review', 'failed'], true);
        $checkOutNeedsReview = $record->check_out_verification !== null
            && in_array($record->check_out_verification->value, ['needs_review', 'failed'], true);

        return $checkInNeedsReview || $checkOutNeedsReview;
    }

    /**
     * @return array<string, string>
     */
    private function legend(): array
    {
        return [
            'Đi làm' => 'is-success',
            'Đi trễ' => 'is-warning',
            'Nghỉ phép' => 'is-active',
            'Vắng' => 'is-danger',
            'Ca tương lai' => 'is-planned',
        ];
    }
}
