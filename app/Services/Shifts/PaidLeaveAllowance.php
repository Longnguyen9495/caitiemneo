<?php

namespace App\Services\Shifts;

use App\Enums\LeaveEntitlement;
use App\Models\MonthlyPaidLeaveDay;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * How many days off a month an employee is still paid for.
 *
 * Kept apart from the approval flow because it is a pay rule, not a workflow
 * step: it is the only thing that decides whether an approved day off costs the
 * shop money, and it is the only writer of `monthly_paid_leave_days`.
 *
 * Callers must already hold the enclosing transaction — the count and the write
 * have to be one indivisible step, or two approvals landing together both read
 * "one day used" and both grant a second paid day.
 */
final class PaidLeaveAllowance
{
    /**
     * Claim one day off against the month's allowance and record it if paid.
     *
     * A day the employee has already been granted stays paid however many
     * shifts were rostered on it, and costs the allowance nothing further:
     * `monthly_paid_leave_days` is unique per employee and date, so a second
     * shift on that date is the same day off, not another one.
     */
    public function claim(User $employee, ?int $branchId, CarbonInterface $leaveDate, User $actor): LeaveEntitlement
    {
        if ($this->alreadyGranted($employee, $leaveDate)) {
            return LeaveEntitlement::Paid;
        }

        if ($this->daysUsedIn($employee, $leaveDate) >= $this->monthlyAllowance()) {
            return LeaveEntitlement::Unpaid;
        }

        MonthlyPaidLeaveDay::query()->create([
            'employee_id' => $employee->getKey(),
            'branch_id' => $branchId,
            'leave_date' => $leaveDate,
            'scheduled_by' => $actor->getKey(),
        ]);

        return LeaveEntitlement::Paid;
    }

    private function alreadyGranted(User $employee, CarbonInterface $leaveDate): bool
    {
        return MonthlyPaidLeaveDay::query()
            ->where('employee_id', $employee->getKey())
            ->whereDate('leave_date', $leaveDate->toDateString())
            ->lockForUpdate()
            ->exists();
    }

    private function daysUsedIn(User $employee, CarbonInterface $leaveDate): int
    {
        return MonthlyPaidLeaveDay::query()
            ->where('employee_id', $employee->getKey())
            ->whereBetween('leave_date', [
                $leaveDate->copy()->startOfMonth()->toDateString(),
                $leaveDate->copy()->endOfMonth()->toDateString(),
            ])
            ->lockForUpdate()
            ->count();
    }

    private function monthlyAllowance(): int
    {
        return (int) config('attendance.paid_leave_days_per_month', 2);
    }
}
