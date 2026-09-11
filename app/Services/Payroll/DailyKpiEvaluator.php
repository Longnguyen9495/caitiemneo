<?php

namespace App\Services\Payroll;

use App\Enums\InvoiceStatus;
use App\Models\DailyKpiTier;
use App\Models\InvoiceItem;
use App\Models\PayrollPolicy;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daily revenue KPI for one employee at one branch.
 *
 * Revenue is the employee's own invoice lines on invoices that were PAID that
 * day at that branch, keyed on `invoices.paid_at` in the application timezone.
 * Cancelled and unpaid invoices contribute nothing, so a later refund simply
 * stops feeding the day.
 *
 * Only the single highest band reached on a day pays out; bands never stack.
 */
class DailyKpiEvaluator
{
    /**
     * @return Collection<int, array{work_date: string, eligible_revenue: string, tier: DailyKpiTier|null, reward_amount: string}>
     */
    public function evaluate(
        int $employeeId,
        int $branchId,
        ?PayrollPolicy $policy,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
    ): Collection {
        $dailyRevenue = $this->dailyRevenue($employeeId, $branchId, $periodStart, $periodEnd);

        if ($policy === null) {
            return $dailyRevenue->map(fn (array $row): array => [
                'work_date' => $row['work_date'],
                'eligible_revenue' => $row['revenue'],
                'tier' => null,
                'reward_amount' => Money::toDecimal(0),
            ])->values();
        }

        $tiers = $policy->dailyKpiTiers;

        return $dailyRevenue->map(function (array $row) use ($tiers): array {
            $tier = $this->highestTierFor(Money::toMinor($row['revenue']), $tiers);

            return [
                'work_date' => $row['work_date'],
                'eligible_revenue' => $row['revenue'],
                'tier' => $tier,
                'reward_amount' => Money::toDecimal($tier === null ? 0 : Money::toMinor($tier->reward_amount)),
            ];
        })->values();
    }

    /**
     * Revenue per calendar day, aggregated by the database.
     *
     * @return Collection<int, array{work_date: string, revenue: string}>
     */
    private function dailyRevenue(int $employeeId, int $branchId, CarbonInterface $periodStart, CarbonInterface $periodEnd): Collection
    {
        $dateExpression = $this->dateExpression('invoices.paid_at');

        return InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoice_items.employee_id', $employeeId)
            ->where('invoices.branch_id', $branchId)
            ->where('invoices.status', InvoiceStatus::Paid->value)
            ->whereBetween('invoices.paid_at', [$periodStart->copy()->startOfDay(), $periodEnd->copy()->endOfDay()])
            ->selectRaw("{$dateExpression} as work_date")
            ->selectRaw('COALESCE(SUM(invoice_items.line_total), 0) as revenue')
            ->groupBy(DB::raw($dateExpression))
            ->orderBy('work_date')
            ->get()
            ->map(fn ($row): array => [
                'work_date' => (string) $row->work_date,
                'revenue' => (string) $row->revenue,
            ]);
    }

    /**
     * `DATE()` exists on MySQL but not on SQLite, which needs `date()`; both
     * accept the lower-case form, so one expression serves both drivers.
     */
    private function dateExpression(string $column): string
    {
        return "date({$column})";
    }

    /**
     * @param  Collection<int, DailyKpiTier>  $tiers
     */
    private function highestTierFor(int $revenueMinor, Collection $tiers): ?DailyKpiTier
    {
        $matched = null;

        foreach ($tiers as $tier) {
            $from = Money::toMinor($tier->revenue_from);
            $to = $tier->revenue_to === null ? null : Money::toMinor($tier->revenue_to);

            $withinBand = $revenueMinor >= $from && ($to === null || $revenueMinor < $to);

            if (! $withinBand) {
                continue;
            }

            if ($matched === null || Money::toMinor($tier->reward_amount) > Money::toMinor($matched->reward_amount)) {
                $matched = $tier;
            }
        }

        return $matched;
    }
}
