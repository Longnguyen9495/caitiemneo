<?php

namespace App\Services\Payroll;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\MonthlyPaidLeaveDay;
use Carbon\CarbonInterface;

/**
 * Produces immutable paid-leave facts for one payroll refresh.
 *
 * A paid-leave day is created when a manager approves one of an employee's
 * first two leave requests in a calendar month. Working on that approved day
 * is an extra work day: it receives normal shift pay through the attendance
 * engine plus the fixed paid-leave-work bonus here.
 */
final class PaidLeaveEvaluator
{
    public const WORKED_PAID_LEAVE_BONUS_MINOR = 20_000_000; // 200,000 VND

    /**
     * @return array{
     *     calendar_days: int,
     *     required_work_days: int,
     *     paid_leave_days: int,
     *     unpaid_leave_days: int,
     *     daily_base_salary_rate_minor: int,
     *     unpaid_leave_deduction_minor: int,
     *     worked_paid_leave_days: int,
     *     worked_paid_leave_bonus_rate_minor: int,
     *     worked_paid_leave_bonus_minor: int,
     *     net_base_salary_minor: int
     * }
     */
    public function evaluate(
        int $employeeId,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        int $baseSalaryMinor,
    ): array {
        $start = $periodStart->copy()->startOfDay();
        $end = $periodEnd->copy()->startOfDay();
        $calendarDays = (int) $start->diffInDays($end) + 1;

        $paidLeaveDates = MonthlyPaidLeaveDay::query()
            ->where('employee_id', $employeeId)
            ->whereDate('leave_date', '>=', $start->toDateString())
            ->whereDate('leave_date', '<=', $end->toDateString())
            ->orderBy('leave_date')
            ->pluck('leave_date')
            ->map(fn (mixed $date): string => (string) $date)
            ->unique()
            ->values();

        $workedDates = AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', AttendanceStatus::payableValues())
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->pluck('work_date')
            ->map(fn (mixed $date): string => (string) $date)
            ->unique();

        $unpaidLeaveDates = AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [AttendanceStatus::Leave->value, AttendanceStatus::Absent->value])
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->pluck('work_date')
            ->map(fn (mixed $date): string => (string) $date)
            ->unique()
            ->diff($paidLeaveDates);

        $paidLeaveDays = $paidLeaveDates->count();
        $requiredWorkDays = max($calendarDays - $paidLeaveDays, 0);
        $workedPaidLeaveDays = $workedDates->intersect($paidLeaveDates)->count();
        // Historical payrolls predate the administrator-controlled leave plan.
        // Preserve their established totals until a concrete plan exists.
        $unpaidLeaveDays = $paidLeaveDays > 0 ? $unpaidLeaveDates->count() : 0;

        $dailyBaseSalaryRateMinor = $calendarDays > 0
            ? intdiv($baseSalaryMinor, $calendarDays)
            : 0;
        $unpaidLeaveDeductionMinor = $dailyBaseSalaryRateMinor * $unpaidLeaveDays;
        $workedPaidLeaveBonusMinor = self::WORKED_PAID_LEAVE_BONUS_MINOR * $workedPaidLeaveDays;

        return [
            'calendar_days' => $calendarDays,
            'required_work_days' => $requiredWorkDays,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'daily_base_salary_rate_minor' => $dailyBaseSalaryRateMinor,
            'unpaid_leave_deduction_minor' => $unpaidLeaveDeductionMinor,
            'worked_paid_leave_days' => $workedPaidLeaveDays,
            'worked_paid_leave_bonus_rate_minor' => self::WORKED_PAID_LEAVE_BONUS_MINOR,
            'worked_paid_leave_bonus_minor' => $workedPaidLeaveBonusMinor,
            'net_base_salary_minor' => max($baseSalaryMinor - $unpaidLeaveDeductionMinor, 0),
        ];
    }
}
