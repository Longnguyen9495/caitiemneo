<?php

namespace App\Services;

use App\Enums\WorkContext;
use App\Models\BranchService;
use App\Models\EmployeeCompensationProfile;
use App\Models\EmployeePolicyAssignment;
use App\Models\PayrollPolicy;
use Carbon\CarbonInterface;

/**
 * Looks up which dated rates and which rule book apply to a person, at a
 * branch, on a date.
 *
 * Precedence is the same everywhere it matters: the most specific record wins.
 */
class CompensationResolver
{
    /**
     * The pay rates of one employee at one branch on one date.
     *
     * A profile written for that branch beats a company-wide profile; if the
     * employee has neither, a zero profile is returned so the caller never has
     * to null-check.
     */
    public function profileFor(int $employeeId, ?int $branchId, CarbonInterface $onDate): EmployeeCompensationProfile
    {
        $profiles = EmployeeCompensationProfile::query()
            ->where('user_id', $employeeId)
            ->effectiveOn($onDate->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        return $profiles->firstWhere('branch_id', $branchId)
            ?? $profiles->firstWhere('branch_id', null)
            ?? new EmployeeCompensationProfile([
                'user_id' => $employeeId,
                'branch_id' => $branchId,
                'base_salary' => 0,
                'shift_rate' => 0,
                'regular_commission_rate' => 0,
                'overtime_commission_rate' => 0,
            ]);
    }

    /**
     * The rule book for one employee at one branch on one date.
     *
     * Employee override, then branch policy, then the company default.
     */
    public function policyFor(int $employeeId, ?int $branchId, CarbonInterface $onDate): ?PayrollPolicy
    {
        $assigned = EmployeePolicyAssignment::query()
            ->with('policy.dailyKpiTiers')
            ->where('user_id', $employeeId)
            ->effectiveOn($onDate->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($assigned?->policy !== null) {
            return $assigned->policy;
        }

        $policies = PayrollPolicy::query()
            ->with('dailyKpiTiers')
            ->effectiveOn($onDate->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        return $policies->firstWhere('branch_id', $branchId)
            ?? $policies->firstWhere('branch_id', null);
    }

    /**
     * The commission percentage for one service line.
     *
     * The branch catalogue may override the rate per service; otherwise the
     * employee's own profile rate for that work context applies.
     *
     * @return array{rate: string, source: string}
     */
    public function commissionRateFor(
        ?int $employeeId,
        ?int $branchId,
        ?int $serviceId,
        WorkContext $context,
        CarbonInterface $onDate,
    ): array {
        if ($serviceId !== null && $branchId !== null) {
            $branchService = BranchService::query()
                ->where('branch_id', $branchId)
                ->where('service_id', $serviceId)
                ->first();

            $rate = $context === WorkContext::Overtime
                ? $branchService?->overtime_commission_rate
                : $branchService?->commission_rate;

            if ($rate !== null) {
                return ['rate' => (string) $rate, 'source' => 'branch_service'];
            }
        }

        if ($employeeId === null) {
            return ['rate' => '0', 'source' => 'none'];
        }

        $profile = $this->profileFor($employeeId, $branchId, $onDate);

        $rate = $context === WorkContext::Overtime
            ? $profile->overtime_commission_rate
            : $profile->regular_commission_rate;

        return ['rate' => (string) $rate, 'source' => 'compensation_profile'];
    }
}
