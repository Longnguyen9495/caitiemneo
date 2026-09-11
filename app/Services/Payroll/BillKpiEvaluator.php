<?php

namespace App\Services\Payroll;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\PayrollPolicy;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * Counts the invoices an employee produced that a manager has verified as
 * counting towards the bill KPI, and applies the policy's reward or penalty.
 *
 * An invoice is counted once: the count is distinct over invoice ids, so an
 * invoice with several of the employee's lines on it cannot inflate the tally.
 * Reward and penalty are independent policy fields — a shop that only wants to
 * withhold the bonus simply leaves the penalty empty.
 */
class BillKpiEvaluator
{
    /**
     * @param  array<int, int>  $branchIds
     * @return array{
     *     required: int|null,
     *     actual: int,
     *     achieved: bool,
     *     reward_amount: string,
     *     penalty_amount: string,
     *     reason: string,
     *     source_policy_id: int|null
     * }
     */
    public function evaluate(
        int $employeeId,
        array $branchIds,
        ?PayrollPolicy $policy,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
    ): array {
        $actual = $this->countQualifiedInvoices($employeeId, $branchIds, $periodStart, $periodEnd);
        $required = $policy?->required_bill_count;

        if ($policy === null || $required === null) {
            return [
                'required' => $required,
                'actual' => $actual,
                'achieved' => false,
                'reward_amount' => Money::toDecimal(0),
                'penalty_amount' => Money::toDecimal(0),
                'reason' => 'Kỳ này không áp dụng KPI số bill.',
                'source_policy_id' => $policy?->getKey(),
            ];
        }

        $achieved = $actual >= $required;

        return [
            'required' => $required,
            'actual' => $actual,
            'achieved' => $achieved,
            'reward_amount' => Money::toDecimal($achieved ? Money::toMinor($policy->bill_kpi_reward_amount) : 0),
            'penalty_amount' => Money::toDecimal($achieved ? 0 : Money::toMinor($policy->missing_bill_penalty_amount)),
            'reason' => sprintf('Đạt %d/%d bill hợp lệ trong kỳ.', $actual, $required),
            'source_policy_id' => $policy->getKey(),
        ];
    }

    /** @param array<int, int> $branchIds */
    private function countQualifiedInvoices(int $employeeId, array $branchIds, CarbonInterface $periodStart, CarbonInterface $periodEnd): int
    {
        if ($branchIds === []) {
            return 0;
        }

        return Invoice::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->where('qualified_for_bill_kpi', true)
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('paid_at', [$periodStart->copy()->startOfDay(), $periodEnd->copy()->endOfDay()])
            ->whereHas('items', fn ($query) => $query->where('employee_id', $employeeId))
            ->distinct()
            ->count('invoices.id');
    }
}
