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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Builds calendar view models for the admin attendance page.
 *
 * Supports two modes:
 * - overview: aggregate counts per day for all employees in branch scope.
 * - employee: full detail for one validated employee in scope.
 *
 * Defense-in-depth: service never trusts controller to pass a valid employee;
 * it re-verifies branch scope and gates every URL by policy.
 */
final readonly class AdminCalendarQuery
{
    public function __construct(private BranchContext $branchContext) {}

    /**
     * Overview mode: count people/records and warnings per day.
     *
     * @param  array<int, int>  $employeeIds  Optional filter to specific employees
     * @param  array<string, string>  $filters  Status/source filters to preserve in URLs
     */
    public function overview(User $actor, ?string $yearMonth = null, array $employeeIds = [], string $baseUrl = '/admin/attendance', array $filters = []): CalendarViewModel
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
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when(($filters['source'] ?? '') !== '', fn ($q) => $q->where('source', $filters['source']))
            ->orderBy('work_date')
            ->get();

        $itemsByDay = $this->buildOverviewItems($records, $actor);

        $queryString = $this->buildQueryString($filters);
        $baseUrlWithFilters = $baseUrl.($queryString !== '' ? '?'.$queryString : '');

        return new CalendarViewModel(
            title: $month->startOfMonth()->locale('vi')->isoFormat('MMMM YYYY'),
            prevUrl: $month->prevMonthUrl($baseUrlWithFilters),
            nextUrl: $month->nextMonthUrl($baseUrlWithFilters),
            currentUrl: $month->currentMonthUrl($baseUrlWithFilters),
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
     *
     * @throws ModelNotFoundException if employee out of scope
     */
    public function forEmployee(User $actor, User $employee, ?string $yearMonth = null, string $baseUrl = '/admin/attendance', array $filters = []): CalendarViewModel
    {
        $branchIds = $this->branchContext->scopeIds() ?: [0];

        // Defense-in-depth: re-verify the employee belongs to branch scope.
        // If not, throw 404-like behaviour by returning empty data via abort.
        $employeeInScope = $employee->branchAssignments()
            ->whereIn('branch_id', $branchIds)
            ->exists();

        if (! $employeeInScope && ! $actor->isOwner()) {
            // Return empty calendar for out-of-scope employee; controller already
            // handles null employee fallback, but this prevents service leaks.
            return $this->emptyEmployeeView($employee, $yearMonth, $baseUrl, $filters);
        }

        $month = new CalendarMonth($yearMonth);
        $from = $month->startOfMonth()->toDateString();
        $to = $month->endOfMonth()->toDateString();

        $records = AttendanceRecord::query()
            ->where('employee_id', $employee->getKey())
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('work_date', [$from, $to])
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when(($filters['source'] ?? '') !== '', fn ($q) => $q->where('source', $filters['source']))
            ->orderBy('work_date')
            ->orderBy('checked_in_at')
            ->get();

        $assignments = ShiftAssignment::query()
            ->where('employee_id', $employee->getKey())
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('work_date', [$from, $to])
            ->with('attendanceRecord')
            ->orderBy('work_date')
            ->get();

        $itemsByDay = $this->buildEmployeeItems($records, $assignments, $actor);

        $employeeQuery = http_build_query(array_merge(
            ['employee_id' => $employee->getKey()],
            $this->filterParams($filters)
        ));
        $baseUrlWithEmployee = $baseUrl.'?'.$employeeQuery;

        return new CalendarViewModel(
            title: $month->startOfMonth()->locale('vi')->isoFormat('MMMM YYYY'),
            prevUrl: $month->prevMonthUrl($baseUrlWithEmployee),
            nextUrl: $month->nextMonthUrl($baseUrlWithEmployee),
            currentUrl: $month->currentMonthUrl($baseUrlWithEmployee),
            weekDayLabels: $month->weekDayLabels(),
            days: $month->days(),
            itemsByDay: $itemsByDay,
            legend: $this->legend(),
            mode: 'admin-employee',
            canInteract: true,
        );
    }

    /**
     * Empty fallback when employee is out of scope.
     */
    private function emptyEmployeeView(User $employee, ?string $yearMonth, string $baseUrl, array $filters): CalendarViewModel
    {
        $month = new CalendarMonth($yearMonth);

        $queryString = $this->buildQueryString($filters);
        $baseUrlWithFilters = $baseUrl.($queryString !== '' ? '?'.$queryString : '');

        return new CalendarViewModel(
            title: $month->startOfMonth()->locale('vi')->isoFormat('MMMM YYYY'),
            prevUrl: $month->prevMonthUrl($baseUrlWithFilters),
            nextUrl: $month->nextMonthUrl($baseUrlWithFilters),
            currentUrl: $month->currentMonthUrl($baseUrlWithFilters),
            weekDayLabels: $month->weekDayLabels(),
            days: $month->days(),
            itemsByDay: [],
            legend: $this->legend(),
            mode: 'admin-employee',
            canInteract: true,
        );
    }

    /**
     * @param  Collection<int, AttendanceRecord>  $records
     * @return array<string, Collection<int, CalendarItem>>
     */
    private function buildOverviewItems(Collection $records, User $actor): array
    {
        /** @var array<string, Collection<int, CalendarItem>> $byDay */
        $byDay = [];

        foreach ($records as $record) {
            $date = $record->work_date->toDateString();

            $item = new CalendarItem(
                recordId: $record->id,
                employeeName: $record->employee?->name,
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
                viewUrl: Gate::forUser($actor)->allows('view', $record)
                    ? route('admin.attendance.edit', $record)
                    : null,
                note: $record->note,
            );

            $byDay[$date] ??= new Collection;
            $byDay[$date]->push($item);
        }

        return $byDay;
    }

    /**
     * @param  Collection<int, AttendanceRecord>  $records
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @return array<string, Collection<int, CalendarItem>>
     */
    private function buildEmployeeItems(Collection $records, Collection $assignments, User $actor): array
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
                viewUrl: Gate::forUser($actor)->allows('view', $record)
                    ? route('admin.attendance.edit', $record)
                    : null,
                editUrl: Gate::forUser($actor)->allows('update', $record)
                    ? route('admin.attendance.update', $record)
                    : null,
                note: $record->note,
            );
            $byDay[$date] ??= new Collection;
            $byDay[$date]->push($item);
        }

        $recordedAssignmentIds = $records->pluck('shift_assignment_id')->filter()->unique()->all();

        foreach ($assignments as $assignment) {
            // Use eager-loaded attendanceRecord to avoid N+1
            if ($assignment->attendanceRecord !== null || in_array($assignment->id, $recordedAssignmentIds, true)) {
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

    /**
     * Build query string from filter params, preserving valid ones.
     */
    private function buildQueryString(array $filters): string
    {
        return http_build_query($this->filterParams($filters));
    }

    /**
     * @return array<string, string>
     */
    private function filterParams(array $filters): array
    {
        $allowed = ['status', 'source'];
        $params = [];

        foreach ($allowed as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $params[$key] = $filters[$key];
            }
        }

        return $params;
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
