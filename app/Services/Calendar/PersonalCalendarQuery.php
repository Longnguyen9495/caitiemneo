<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\AttendanceRecord;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Support\CalendarItem;
use App\Support\CalendarMonth;
use App\Support\CalendarViewModel;
use Illuminate\Support\Collection;

/**
 * Builds a calendar view model for one employee's own attendance.
 *
 * Always binds to the signed-in user; ignores any employee_id from request.
 */
final readonly class PersonalCalendarQuery
{
    public function for(User $employee, ?string $yearMonth = null, string $baseUrl = '/cham-cong'): CalendarViewModel
    {
        $month = new CalendarMonth($yearMonth);
        $from = $month->startOfMonth()->toDateString();
        $to = $month->endOfMonth()->toDateString();

        $records = $this->records($employee, $from, $to);
        $assignments = $this->assignments($employee, $from, $to);

        $itemsByDay = $this->buildItems($records, $assignments);

        return new CalendarViewModel(
            title: $month->startOfMonth()->locale('vi')->isoFormat('MMMM YYYY'),
            prevUrl: $month->prevMonthUrl($baseUrl),
            nextUrl: $month->nextMonthUrl($baseUrl),
            currentUrl: $month->currentMonthUrl($baseUrl),
            weekDayLabels: $month->weekDayLabels(),
            days: $month->days(),
            itemsByDay: $itemsByDay,
            legend: $this->legend(),
            mode: 'personal',
            canInteract: true,
        );
    }

    /**
     * @return Collection<int, AttendanceRecord>
     */
    private function records(User $employee, string $from, string $to): Collection
    {
        return AttendanceRecord::query()
            ->where('employee_id', $employee->getKey())
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->orderBy('checked_in_at')
            ->get();
    }

    /**
     * @return Collection<int, ShiftAssignment>
     */
    private function assignments(User $employee, string $from, string $to): Collection
    {
        return ShiftAssignment::query()
            ->where('employee_id', $employee->getKey())
            ->whereBetween('work_date', [$from, $to])
            ->with('attendanceRecord')
            ->orderBy('work_date')
            ->orderBy('planned_start_at')
            ->get();
    }

    /**
     * Build calendar items grouped by ISO date.
     *
     * @param  Collection<int, AttendanceRecord>  $records
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @return array<string, Collection<int, CalendarItem>>
     */
    private function buildItems(Collection $records, Collection $assignments): array
    {
        /** @var array<string, Collection<int, CalendarItem>> $byDay */
        $byDay = [];

        // Map records to items
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
                note: $record->note,
            );
            $byDay[$date] ??= new Collection;
            $byDay[$date]->push($item);
        }

        // Add future assignments that have no record yet
        $recordedAssignmentIds = $records->pluck('shift_assignment_id')->filter()->unique()->all();

        foreach ($assignments as $assignment) {
            if ($assignment->attendanceRecord !== null) {
                continue; // Already covered by record query
            }
            // Only show future assignments (or today) without a record
            if (in_array($assignment->id, $recordedAssignmentIds, true)) {
                continue;
            }

            $date = $assignment->work_date->toDateString();
            $item = new CalendarItem(
                shiftName: $assignment->shift_name,
                plannedStartAt: $assignment->planned_start_at?->format('H:i'),
                plannedEndAt: $assignment->planned_end_at?->format('H:i'),
            );
            $byDay[$date] ??= new Collection;
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
