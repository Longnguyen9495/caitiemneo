<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What one employee's clock-in screen should show, and act on, right now.
 *
 * The screen never asks the browser which shift is being worked. This service
 * answers that from the roster and the server clock, which is what stops a
 * crafted request from clocking into somebody else's shift or another branch.
 */
final class ShiftBoard
{
    /**
     * @return array{
     *     now: CarbonInterface,
     *     openRecord: AttendanceRecord|null,
     *     actionableAssignment: ShiftAssignment|null,
     *     todayAssignments: Collection<int, ShiftAssignment>,
     *     upcomingAssignments: Collection<int, ShiftAssignment>,
     *     recentRecords: Collection<int, AttendanceRecord>
     * }
     */
    public function for(User $employee, ?CarbonInterface $now = null): array
    {
        $moment = $now ?? now();

        return [
            'now' => $moment,
            'openRecord' => $this->openRecord($employee),
            'actionableAssignment' => $this->actionableAssignment($employee, $moment),
            'todayAssignments' => $this->todayAssignments($employee, $moment),
            'upcomingAssignments' => $this->upcomingAssignments($employee, $moment),
            'recentRecords' => $this->recentRecords($employee, $moment),
        ];
    }

    /**
     * The shift the employee is currently inside, if any.
     *
     * Looked up across the last two days rather than today, because an
     * overnight shift belongs to yesterday's business date but is closed
     * today.
     */
    public function openRecord(User $employee): ?AttendanceRecord
    {
        return AttendanceRecord::query()
            ->with(['branch', 'shiftAssignment'])
            ->where('employee_id', $employee->getKey())
            ->missingCheckOut()
            ->orderByDesc('checked_in_at')
            ->first();
    }

    /**
     * The roster entry the Vào ca button would act on.
     *
     * Only a shift whose clock-in window is open right now qualifies, so the
     * button is never offered for a shift that cannot actually be started.
     */
    public function actionableAssignment(User $employee, ?CarbonInterface $now = null): ?ShiftAssignment
    {
        $moment = $now ?? now();

        return $this->assignmentsAround($employee, $moment)
            ->reject(fn (ShiftAssignment $assignment): bool => $assignment->attendanceRecord !== null)
            ->first(fn (ShiftAssignment $assignment): bool => $moment->gte($assignment->earliestCheckInAt())
                && $moment->lte($assignment->latestCheckInAt()));
    }

    /** @return Collection<int, ShiftAssignment> */
    public function todayAssignments(User $employee, ?CarbonInterface $now = null): Collection
    {
        $moment = $now ?? now();

        return $this->assignmentsAround($employee, $moment)
            ->filter(fn (ShiftAssignment $assignment): bool => $assignment->work_date->isSameDay($moment)
                || ($assignment->crossesMidnight() && $assignment->planned_end_at->isSameDay($moment)))
            ->values();
    }

    /** @return Collection<int, ShiftAssignment> */
    public function upcomingAssignments(User $employee, ?CarbonInterface $now = null, int $days = 7): Collection
    {
        $moment = $now ?? now();

        return ShiftAssignment::query()
            ->with(['branch', 'attendanceRecord'])
            ->where('employee_id', $employee->getKey())
            ->whereBetween('work_date', [
                $moment->copy()->addDay()->startOfDay(),
                $moment->copy()->addDays($days)->endOfDay(),
            ])
            ->orderBy('planned_start_at')
            ->get();
    }

    /** @return Collection<int, AttendanceRecord> */
    public function recentRecords(User $employee, ?CarbonInterface $now = null, int $days = 7): Collection
    {
        $moment = $now ?? now();
        $firstDay = $moment->copy()->subDays(max(0, $days - 1))->startOfDay();

        return AttendanceRecord::query()
            ->with('branch')
            ->where('employee_id', $employee->getKey())
            ->whereBetween('work_date', [
                $firstDay,
                $moment->copy()->endOfDay(),
            ])
            ->orderByDesc('work_date')
            ->orderByDesc('checked_in_at')
            ->limit(20)
            ->get();
    }

    /**
     * Yesterday, today and tomorrow's roster for this employee.
     *
     * The window is wider than "today" so an overnight shift and a shift that
     * may be started up to 30 minutes early both stay reachable.
     *
     * @return Collection<int, ShiftAssignment>
     */
    private function assignmentsAround(User $employee, CarbonInterface $moment): Collection
    {
        return ShiftAssignment::query()
            ->with(['branch', 'attendanceRecord'])
            ->where('employee_id', $employee->getKey())
            ->whereBetween('work_date', [
                $moment->copy()->subDay()->startOfDay(),
                $moment->copy()->addDay()->endOfDay(),
            ])
            ->orderBy('planned_start_at')
            ->get();
    }
}
