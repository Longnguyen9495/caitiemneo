<?php

namespace App\Services\Payroll;

use App\Enums\PayrollStatus;
use App\Models\Payroll;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Protects shifts that a closed payroll has already counted.
 *
 * A finalised or paid payroll is a snapshot of what the employee was told they
 * would be paid. Anything that could change that number has to be refused, so
 * every pay-affecting write to attendance asks here first.
 */
final class PayrollLockGuard
{
    public function isLocked(int $employeeId, CarbonInterface|string $workDate): bool
    {
        $date = $workDate instanceof CarbonInterface ? $workDate->toDateString() : (string) $workDate;

        return Payroll::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [PayrollStatus::Finalized->value, PayrollStatus::Paid->value])
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->exists();
    }

    /**
     * @param  string  $field  the form field the refusal should attach to
     *
     * @throws ValidationException
     */
    public function assertUnlocked(int $employeeId, CarbonInterface|string $workDate, string $field = 'work_date'): void
    {
        if (! $this->isLocked($employeeId, $workDate)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'Ca này đã nằm trong một bảng lương đã chốt nên không thể thay đổi.',
        ]);
    }
}
