<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\CashTransactionType;
use App\Enums\InventoryMovementType;
use App\Enums\InvoiceStatus;
use App\Enums\PayrollStatus;
use App\Models\Branch;
use App\Models\CashTransaction;
use App\Models\DailyKpiResult;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payroll;
use App\Models\Product;
use App\Support\Money;
use App\Support\ReportPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the reporting module.
 *
 * Two different clocks are deliberately kept apart:
 * - revenue is measured on `invoices.paid_at`;
 * - cash flow is measured on `cash_transactions.occurred_at`.
 *
 * Every figure is aggregated by the database; no collection is ever loaded just
 * to be grouped in PHP.
 */
class ReportService
{
    /**
     * @param  array<int, int>|null  $branchIds  null means every branch
     * @return array<string, mixed>
     */
    public function summary(ReportPeriod $period, ?array $branchIds = null): array
    {
        $cash = $this->cashFlow($period, $branchIds);
        $revenueMinor = Money::toMinor($this->paidRevenue($period, $branchIds));
        $inventoryCostMinor = Money::toMinor($this->inventoryPurchaseCost($period, $branchIds));
        $payrollCostMinor = Money::toMinor($this->payrollCost($period, $branchIds));

        return [
            'revenue' => Money::toDecimal($revenueMinor),
            'invoice_count' => $this->paidInvoiceCount($period, $branchIds),
            'cash_income' => $cash['income'],
            'cash_expense' => $cash['expense'],
            'cash_net' => $cash['net'],
            'inventory_cost' => Money::toDecimal($inventoryCostMinor),
            'payroll_cost' => Money::toDecimal($payrollCostMinor),
            'gross_margin' => Money::toDecimal($revenueMinor - $inventoryCostMinor - $payrollCostMinor),
        ];
    }

    /** @param array<int, int>|null $branchIds */
    public function paidRevenue(ReportPeriod $period, ?array $branchIds = null): string
    {
        return (string) Invoice::query()
            ->paidBetween($period->from, $period->to)
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->sum('total');
    }

    /** @param array<int, int>|null $branchIds */
    public function paidInvoiceCount(ReportPeriod $period, ?array $branchIds = null): int
    {
        return Invoice::query()
            ->paidBetween($period->from, $period->to)
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->count();
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array{income: string, expense: string, net: string}
     */
    public function cashFlow(ReportPeriod $period, ?array $branchIds = null): array
    {
        $row = CashTransaction::query()
            ->active()
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->whereBetween('occurred_at', [$period->from, $period->to])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as income_total', [CashTransactionType::Income->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as expense_total', [CashTransactionType::Expense->value])
            ->first();

        $incomeMinor = Money::toMinor($row?->income_total ?? 0);
        $expenseMinor = Money::toMinor($row?->expense_total ?? 0);

        return [
            'income' => Money::toDecimal($incomeMinor),
            'expense' => Money::toDecimal($expenseMinor),
            'net' => Money::toDecimal($incomeMinor - $expenseMinor),
        ];
    }

    /**
     * Appointment volume of the period keyed by raw status value.
     *
     * The query builder is used on purpose: grouping needs the stored string, not
     * the enum instance the Eloquent cast would hand back.
     *
     * @return array<string, int>
     */
    public function appointmentsByStatus(ReportPeriod $period, ?array $branchIds = null): array
    {
        return DB::table('appointments')
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->whereBetween('starts_at', [$period->from, $period->to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /** @param array<int, int>|null $branchIds */
    public function inventoryPurchaseCost(ReportPeriod $period, ?array $branchIds = null): string
    {
        return (string) InventoryMovement::query()
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->where('type', InventoryMovementType::In->value)
            ->whereBetween('occurred_at', [$period->from, $period->to])
            ->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as total')
            ->value('total');
    }

    /**
     * Salary already paid out, attributed to the branch whose cash box paid it.
     *
     * @param  array<int, int>|null  $branchIds
     */
    public function payrollCost(ReportPeriod $period, ?array $branchIds = null): string
    {
        return (string) Payroll::query()
            ->where('status', PayrollStatus::Paid->value)
            ->when($branchIds !== null, fn ($query) => $query->where(fn ($inner) => $inner
                ->whereIn('paying_branch_id', $branchIds)
                ->orWhereHas('allocations', fn ($allocation) => $allocation->whereIn('branch_id', $branchIds))))
            ->whereBetween('paid_at', [$period->from, $period->to])
            ->sum('final_total');
    }

    /** @return Collection<int, object> */
    public function revenueByService(ReportPeriod $period, int $limit = 0, ?array $branchIds = null): Collection
    {
        $query = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('services', 'services.id', '=', 'invoice_items.service_id')
            ->when($branchIds !== null, fn ($inner) => $inner->whereIn('invoices.branch_id', $branchIds))
            ->where('invoices.status', InvoiceStatus::Paid->value)
            ->whereBetween('invoices.paid_at', [$period->from, $period->to])
            ->selectRaw('COALESCE(services.name, invoice_items.name) as service_name')
            ->selectRaw('COALESCE(SUM(invoice_items.quantity), 0) as quantity_total')
            ->selectRaw('COALESCE(SUM(invoice_items.line_total), 0) as revenue_total')
            ->groupBy('service_name')
            ->orderByDesc('revenue_total');

        return $limit > 0 ? $query->limit($limit)->get() : $query->get();
    }

    /** @return Collection<int, object> */
    public function revenueByEmployee(ReportPeriod $period, int $limit = 0, ?array $branchIds = null): Collection
    {
        $query = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('users', 'users.id', '=', 'invoice_items.employee_id')
            ->when($branchIds !== null, fn ($inner) => $inner->whereIn('invoices.branch_id', $branchIds))
            ->where('invoices.status', InvoiceStatus::Paid->value)
            ->whereBetween('invoices.paid_at', [$period->from, $period->to])
            ->selectRaw("COALESCE(users.name, 'Chưa phân công') as employee_name")
            ->selectRaw('COALESCE(SUM(invoice_items.line_total), 0) as revenue_total')
            ->selectRaw("COALESCE(SUM(CASE WHEN invoice_items.work_context = 'regular' THEN invoice_items.commission_amount ELSE 0 END), 0) as regular_commission_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN invoice_items.work_context = 'overtime' THEN invoice_items.commission_amount ELSE 0 END), 0) as overtime_commission_total")
            ->selectRaw('COALESCE(SUM(invoice_items.commission_amount), 0) as commission_total')
            ->groupBy('employee_name')
            ->orderByDesc('revenue_total');

        return $limit > 0 ? $query->limit($limit)->get() : $query->get();
    }

    /**
     * One summary row per branch, for side-by-side comparison.
     *
     * Each branch is measured with exactly the same queries as the single-branch
     * view, so the numbers always reconcile with the per-branch screens.
     *
     * @param  Collection<int, Branch>  $branches
     * @return Collection<int, array<string, mixed>>
     */
    public function branchComparison(ReportPeriod $period, $branches): Collection
    {
        return $branches->map(function ($branch) use ($period): array {
            $branchIds = [$branch->getKey()];
            $appointments = $this->appointmentsByStatus($period, $branchIds);
            $totalAppointments = array_sum($appointments);

            $lost = ($appointments[AppointmentStatus::Cancelled->value] ?? 0)
                + ($appointments[AppointmentStatus::NoShow->value] ?? 0);

            $commission = $this->commissionTotals($period, $branchIds);

            return [
                'branch' => $branch,
                'summary' => $this->summary($period, $branchIds),
                'appointments' => $totalAppointments,
                'completed' => $appointments[AppointmentStatus::Completed->value] ?? 0,
                'lost' => $lost,
                'lost_rate' => $totalAppointments > 0 ? round($lost / $totalAppointments * 100, 1) : 0.0,
                'regular_commission' => $commission['regular'],
                'overtime_commission' => $commission['overtime'],
                'kpi_bonus' => $this->kpiBonusTotal($period, $branchIds),
                'low_stock' => Product::query()->where('is_active', true)->lowStock($branch->getKey())->count(),
            ];
        })->values();
    }

    /**
     * Commission split by work context.
     *
     * @param  array<int, int>|null  $branchIds
     * @return array{regular: string, overtime: string}
     */
    public function commissionTotals(ReportPeriod $period, ?array $branchIds = null): array
    {
        $row = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->when($branchIds !== null, fn ($query) => $query->whereIn('invoices.branch_id', $branchIds))
            ->where('invoices.status', InvoiceStatus::Paid->value)
            ->whereBetween('invoices.paid_at', [$period->from, $period->to])
            ->selectRaw("COALESCE(SUM(CASE WHEN invoice_items.work_context = 'regular' THEN invoice_items.commission_amount ELSE 0 END), 0) as regular_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN invoice_items.work_context = 'overtime' THEN invoice_items.commission_amount ELSE 0 END), 0) as overtime_total")
            ->first();

        return [
            'regular' => Money::toDecimal(Money::toMinor($row?->regular_total ?? 0)),
            'overtime' => Money::toDecimal(Money::toMinor($row?->overtime_total ?? 0)),
        ];
    }

    /**
     * KPI money actually awarded in the period, read from the payroll snapshots.
     *
     * @param  array<int, int>|null  $branchIds
     */
    public function kpiBonusTotal(ReportPeriod $period, ?array $branchIds = null): string
    {
        $daily = DailyKpiResult::query()
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->whereBetween('work_date', [$period->from, $period->to])
            ->sum('reward_amount');

        return Money::toDecimal(Money::toMinor($daily));
    }
}
