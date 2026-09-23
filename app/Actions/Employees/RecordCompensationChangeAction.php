<?php

namespace App\Actions\Employees;

use App\Models\EmployeeCompensationProfile;
use App\Models\User;

/**
 * Write a new pay rate without rewriting what has already been paid.
 *
 * The salary columns on `users` only exist to fill the form. Invoices and
 * payrolls read the profile in force on the business date they are about, so
 * changing somebody's rate must open a new profile from today rather than edit
 * the old one — otherwise last month's closed payroll would silently recompute
 * at this month's numbers, and the figure the employee was told they would be
 * paid would no longer be findable anywhere.
 *
 * Two changes on the same day are the one exception: nothing was earned between
 * them, so the second simply corrects the first.
 */
final class RecordCompensationChangeAction
{
    /**
     * @param  array{base_salary: mixed, shift_rate: mixed, commission_rate: mixed}  $rates
     */
    public function handle(User $employee, array $rates, User $actor): EmployeeCompensationProfile
    {
        $today = now()->toDateString();
        $current = $this->currentCompanyWideProfile($employee, $today);

        $columns = [
            'base_salary' => $rates['base_salary'],
            'shift_rate' => $rates['shift_rate'],
            'regular_commission_rate' => $rates['commission_rate'],
            'overtime_commission_rate' => $rates['commission_rate'],
        ];

        if ($current?->effective_from?->isSameDay($today)) {
            $current->update($columns);

            return $current;
        }

        $current?->update(['effective_to' => now()->subDay()->toDateString()]);

        return $employee->compensationProfiles()->create($columns + [
            'branch_id' => null,
            'effective_from' => $today,
            'effective_to' => null,
            'created_by' => $actor->getKey(),
        ]);
    }

    /** The open, all-branches profile this account is paid by today. */
    private function currentCompanyWideProfile(User $employee, string $onDate): ?EmployeeCompensationProfile
    {
        return EmployeeCompensationProfile::query()
            ->where('user_id', $employee->getKey())
            ->whereNull('branch_id')
            ->effectiveOn($onDate)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}
