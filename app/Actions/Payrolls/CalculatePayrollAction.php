<?php

namespace App\Actions\Payrolls;

use App\Enums\AttendanceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PayrollAdjustmentCategory as Category;
use App\Enums\PayrollAdjustmentDirection as Direction;
use App\Enums\RoundingRule;
use App\Enums\WorkContext;
use App\Models\AttendanceRecord;
use App\Models\DailyKpiResult;
use App\Models\EmployeeCompensationProfile;
use App\Models\InvoiceItem;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollAllocation;
use App\Models\PayrollPolicy;
use App\Models\PendingPayrollCorrection;
use App\Services\CompensationResolver;
use App\Services\Payroll\AttendanceEvaluator;
use App\Services\Payroll\BillKpiEvaluator;
use App\Services\Payroll\DailyKpiEvaluator;
use App\Services\Payroll\PaidLeaveEvaluator;
use App\Support\Allocator;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds every derived figure of a draft payroll.
 *
 * The engine owns four kinds of adjustment row — attendance bonus, daily KPI,
 * bill KPI bonus and missing-bill penalty — and replaces exactly those on each
 * run. Anything a human typed in survives untouched.
 *
 * Nothing here hard-codes an amount or a threshold: every number is read from
 * the {@see PayrollPolicy} and
 * {@see EmployeeCompensationProfile} that were in force on the day.
 */
class CalculatePayrollAction
{
    /** Marks the adjustment rows this engine creates from parked corrections. */
    private const CORRECTION_SOURCE = 'pending_correction';

    public function __construct(
        private CompensationResolver $compensation,
        private AttendanceEvaluator $attendance,
        private DailyKpiEvaluator $dailyKpi,
        private BillKpiEvaluator $billKpi,
        private PaidLeaveEvaluator $paidLeave,
    ) {}

    public function refresh(Payroll $payroll): Payroll
    {
        return DB::transaction(function () use ($payroll): Payroll {
            $payroll->loadMissing('employee');

            $periodStart = $payroll->period_start;
            $periodEnd = $payroll->period_end;
            $employeeId = (int) $payroll->employee_id;

            $branchIds = $this->branchesInvolved($payroll);
            $policy = $this->compensation->policyFor($employeeId, $payroll->paying_branch_id ?? ($branchIds[0] ?? null), $periodStart);

            $payroll->forceFill(['payroll_policy_id' => $policy?->getKey()])->save();

            $this->clearEngineRows($payroll);

            $shiftCounts = $this->shiftCountsByBranch($employeeId, $branchIds, $periodStart, $periodEnd);
            $attendance = $this->attendance->evaluate($employeeId, $policy, $periodStart, $periodEnd);
            $bill = $this->billKpi->evaluate($employeeId, $branchIds, $policy, $periodStart, $periodEnd);

            $baseProfile = $this->compensation->profileFor($employeeId, $payroll->paying_branch_id, $periodStart);
            $baseSalaryMinor = Money::toMinor($baseProfile->base_salary);
            $paidLeave = $this->paidLeave->evaluate($employeeId, $periodStart, $periodEnd, $baseSalaryMinor);
            $weights = $this->allocationWeights($branchIds, $shiftCounts);

            // The unpaid-leave amount is recorded as a deduction adjustment below.
            // Keep allocations at the gross base so that deduction is applied exactly once.
            $baseShares = Allocator::distribute($baseSalaryMinor, $weights);
            $attendanceShares = Allocator::distribute(Money::toMinor($attendance['bonus_amount']), $weights);
            $billRewardShares = Allocator::distribute(Money::toMinor($bill['reward_amount']), $weights);
            $billPenaltyShares = Allocator::distribute(Money::toMinor($bill['penalty_amount']), $weights);
            $workedPaidLeaveBonusShares = Allocator::distribute($paidLeave['worked_paid_leave_bonus_minor'], $weights);
            $unpaidLeaveDeductionShares = Allocator::distribute($paidLeave['unpaid_leave_deduction_minor'], $weights);

            $allocations = [];

            foreach ($branchIds as $branchId) {
                $allocations[$branchId] = $this->buildAllocation(
                    $payroll,
                    $employeeId,
                    $branchId,
                    $periodStart,
                    $periodEnd,
                    (int) ($shiftCounts[$branchId] ?? 0),
                    (int) ($baseShares[$branchId] ?? 0),
                    (int) ($attendanceShares[$branchId] ?? 0),
                    (int) ($billRewardShares[$branchId] ?? 0),
                    (int) ($billPenaltyShares[$branchId] ?? 0),
                    (int) ($workedPaidLeaveBonusShares[$branchId] ?? 0),
                    (int) ($unpaidLeaveDeductionShares[$branchId] ?? 0),
                    $policy,
                    $attendance,
                    $bill,
                );
            }

            $this->applyPendingCorrections($payroll, $allocations);
            $this->applyManualAdjustmentTotals($payroll, $allocations);
            $this->finaliseTotals($payroll, $allocations, $attendance, $bill, $policy, $baseSalaryMinor, $paidLeave);

            return $payroll->refresh();
        });
    }

    /**
     * Which branches this payroll has to account for.
     *
     * Any branch the employee clocked in at, or produced paid revenue at,
     * during the period, plus the paying branch so a payroll with no activity
     * still has one allocation to hang manual lines off.
     *
     * @return array<int, int>
     */
    private function branchesInvolved(Payroll $payroll): array
    {
        $employeeId = (int) $payroll->employee_id;
        $from = $payroll->period_start->copy()->startOfDay();
        $to = $payroll->period_end->copy()->endOfDay();

        $fromAttendance = AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$from, $to])
            ->distinct()
            ->pluck('branch_id');

        $fromRevenue = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoice_items.employee_id', $employeeId)
            ->where('invoices.status', InvoiceStatus::Paid->value)
            ->whereBetween('invoices.paid_at', [$from, $to])
            ->distinct()
            ->pluck('invoices.branch_id');

        $fallback = collect([
            $payroll->paying_branch_id,
            $payroll->employee?->primaryBranchId($payroll->period_start->toDateString()),
        ])->filter();

        $branchIds = $fromAttendance->merge($fromRevenue)->filter();

        if ($branchIds->isEmpty()) {
            $branchIds = $fallback;
        }

        return $branchIds->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
    }

    /**
     * Payable shift totals per branch, in integer hundredths.
     *
     * @param  array<int, int>  $branchIds
     * @return array<int, int>
     */
    private function shiftCountsByBranch(int $employeeId, array $branchIds, CarbonInterface $periodStart, CarbonInterface $periodEnd): array
    {
        if ($branchIds === []) {
            return [];
        }

        $rows = AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', AttendanceStatus::payableValues())
            ->whereBetween('work_date', [$periodStart->copy()->startOfDay(), $periodEnd->copy()->endOfDay()])
            ->selectRaw('branch_id, COALESCE(SUM(shift_value), 0) as shift_total')
            ->groupBy('branch_id')
            ->pluck('shift_total', 'branch_id');

        $counts = [];

        foreach ($branchIds as $branchId) {
            $counts[$branchId] = Money::toMinor($rows[$branchId] ?? 0);
        }

        return $counts;
    }

    /**
     * Weights used to split period-wide money across branches.
     *
     * Shift volume is the fairest proxy for "where the work happened"; when no
     * shifts were recorded the split falls back to an even one.
     *
     * @param  array<int, int>  $branchIds
     * @param  array<int, int>  $shiftCounts
     * @return array<int, int>
     */
    private function allocationWeights(array $branchIds, array $shiftCounts): array
    {
        $weights = [];

        foreach ($branchIds as $branchId) {
            $weights[$branchId] = max(0, (int) ($shiftCounts[$branchId] ?? 0));
        }

        return $weights;
    }

    /**
     * Build one branch allocation: shift pay, commissions, KPI and the engine's
     * own adjustment rows for that branch.
     *
     * @param  array<string, mixed>  $attendance
     * @param  array<string, mixed>  $bill
     */
    private function buildAllocation(
        Payroll $payroll,
        int $employeeId,
        int $branchId,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        int $shiftCountMinor,
        int $baseShareMinor,
        int $attendanceShareMinor,
        int $billRewardMinor,
        int $billPenaltyMinor,
        int $workedPaidLeaveBonusMinor,
        int $unpaidLeaveDeductionMinor,
        ?PayrollPolicy $policy,
        array $attendance,
        array $bill,
    ): PayrollAllocation {
        $profile = $this->compensation->profileFor($employeeId, $branchId, $periodStart);

        $shiftPayMinor = Money::multiplyByQuantity(
            Money::toMinor($profile->shift_rate),
            Money::toDecimal($shiftCountMinor),
        );

        $revenue = $this->revenueByContext($employeeId, $branchId, $periodStart, $periodEnd);

        $allocation = PayrollAllocation::query()->updateOrCreate(
            ['payroll_id' => $payroll->getKey(), 'branch_id' => $branchId],
            [
                'base_salary_amount' => Money::toDecimal($baseShareMinor),
                'shift_count' => Money::toDecimal($shiftCountMinor),
                'shift_pay' => Money::toDecimal($shiftPayMinor),
                'attendance_bonus' => Money::toDecimal($attendanceShareMinor),
                'regular_revenue' => Money::toDecimal($revenue['regular_revenue']),
                'regular_commission_pay' => Money::toDecimal($revenue['regular_commission']),
                'overtime_revenue' => Money::toDecimal($revenue['overtime_revenue']),
                'overtime_commission_pay' => Money::toDecimal($revenue['overtime_commission']),
                'bill_kpi_bonus' => Money::toDecimal($billRewardMinor),
            ],
        );

        $dailyKpiMinor = $this->storeDailyKpi($payroll, $allocation, $employeeId, $branchId, $policy, $periodStart, $periodEnd);

        $allocation->forceFill(['daily_kpi_bonus' => Money::toDecimal($dailyKpiMinor)])->save();

        $this->writeEngineAdjustments(
            $payroll,
            $allocation,
            $attendanceShareMinor,
            $dailyKpiMinor,
            $billRewardMinor,
            $billPenaltyMinor,
            $workedPaidLeaveBonusMinor,
            $unpaidLeaveDeductionMinor,
            $attendance,
            $bill,
        );

        return $allocation;
    }

    /**
     * Revenue and commission of one employee at one branch, split by work
     * context, straight from the snapshots on the invoice lines.
     *
     * @return array{regular_revenue: int, regular_commission: int, overtime_revenue: int, overtime_commission: int}
     */
    private function revenueByContext(int $employeeId, int $branchId, CarbonInterface $periodStart, CarbonInterface $periodEnd): array
    {
        $rows = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoice_items.employee_id', $employeeId)
            ->where('invoices.branch_id', $branchId)
            ->where('invoices.status', InvoiceStatus::Paid->value)
            ->whereBetween('invoices.paid_at', [$periodStart->copy()->startOfDay(), $periodEnd->copy()->endOfDay()])
            ->selectRaw('invoice_items.work_context as context')
            ->selectRaw('COALESCE(SUM(invoice_items.line_total), 0) as revenue_total')
            ->selectRaw('COALESCE(SUM(invoice_items.commission_amount), 0) as commission_total')
            ->groupBy('invoice_items.work_context')
            ->get()
            ->keyBy('context');

        $regular = $rows[WorkContext::Regular->value] ?? null;
        $overtime = $rows[WorkContext::Overtime->value] ?? null;

        return [
            'regular_revenue' => Money::toMinor($regular->revenue_total ?? 0),
            'regular_commission' => Money::toMinor($regular->commission_total ?? 0),
            'overtime_revenue' => Money::toMinor($overtime->revenue_total ?? 0),
            'overtime_commission' => Money::toMinor($overtime->commission_total ?? 0),
        ];
    }

    /** Snapshot every KPI day of this branch and return the total reward. */
    private function storeDailyKpi(
        Payroll $payroll,
        PayrollAllocation $allocation,
        int $employeeId,
        int $branchId,
        ?PayrollPolicy $policy,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
    ): int {
        $days = $this->dailyKpi->evaluate($employeeId, $branchId, $policy, $periodStart, $periodEnd);
        $totalMinor = 0;

        foreach ($days as $day) {
            $rewardMinor = Money::toMinor($day['reward_amount']);
            $totalMinor += $rewardMinor;

            DailyKpiResult::query()->create([
                'payroll_id' => $payroll->getKey(),
                'payroll_allocation_id' => $allocation->getKey(),
                'branch_id' => $branchId,
                'employee_id' => $employeeId,
                'work_date' => $day['work_date'],
                'eligible_revenue' => $day['eligible_revenue'],
                'achieved_tier_id' => $day['tier']?->getKey(),
                'reward_amount' => $day['reward_amount'],
                'source_policy_id' => $policy?->getKey(),
            ]);
        }

        return $totalMinor;
    }

    /**
     * Remove the rows this engine owns before rebuilding them.
     *
     * Only automatic rows in engine-owned categories are cleared, which is what
     * makes a recalculation idempotent without ever eating a manual line.
     */
    private function clearEngineRows(Payroll $payroll): void
    {
        PayrollAdjustment::query()
            ->where('payroll_id', $payroll->getKey())
            ->automatic()
            ->where(fn ($query) => $query
                ->whereIn('category', Category::engineOwnedValues())
                ->orWhere('source_type', self::CORRECTION_SOURCE))
            ->delete();

        DailyKpiResult::query()->where('payroll_id', $payroll->getKey())->delete();
    }

    /**
     * Write the engine's own adjustment lines for one branch.
     *
     * @param  array<string, mixed>  $attendance
     * @param  array<string, mixed>  $bill
     */
    private function writeEngineAdjustments(
        Payroll $payroll,
        PayrollAllocation $allocation,
        int $attendanceMinor,
        int $dailyKpiMinor,
        int $billRewardMinor,
        int $billPenaltyMinor,
        int $workedPaidLeaveBonusMinor,
        int $unpaidLeaveDeductionMinor,
        array $attendance,
        array $bill,
    ): void {
        $this->writeAdjustment($payroll, $allocation, Category::AttendanceBonus, Direction::Earning, $attendanceMinor, 'Thưởng chuyên cần. '.$attendance['reason'], $attendance['source_policy_id']);
        $this->writeAdjustment($payroll, $allocation, Category::DailyKpiBonus, Direction::Earning, $dailyKpiMinor, 'Thưởng KPI doanh thu theo ngày.', null);
        $this->writeAdjustment($payroll, $allocation, Category::BillKpiBonus, Direction::Earning, $billRewardMinor, 'Thưởng KPI số bill. '.$bill['reason'], $bill['source_policy_id']);
        $this->writeAdjustment($payroll, $allocation, Category::MissingBillPenalty, Direction::Deduction, $billPenaltyMinor, 'Phạt không đạt KPI số bill. '.$bill['reason'], $bill['source_policy_id']);
        $this->writeAdjustment($payroll, $allocation, Category::WorkedPaidLeaveBonus, Direction::Earning, $workedPaidLeaveBonusMinor, 'Thưởng đi làm vào ngày nghỉ hưởng lương đã được xếp.', null);
        $this->writeAdjustment($payroll, $allocation, Category::UnpaidLeaveDeduction, Direction::Deduction, $unpaidLeaveDeductionMinor, 'Khấu trừ lương cơ bản cho ngày chưa làm ngoài ngày nghỉ hưởng lương đã xếp.', null);
    }

    private function writeAdjustment(
        Payroll $payroll,
        PayrollAllocation $allocation,
        Category $category,
        Direction $direction,
        int $amountMinor,
        string $description,
        ?int $policyId,
    ): void {
        if ($amountMinor <= 0) {
            return;
        }

        PayrollAdjustment::query()->create([
            'payroll_id' => $payroll->getKey(),
            'payroll_allocation_id' => $allocation->getKey(),
            'branch_id' => $allocation->branch_id,
            'category' => $category,
            'direction' => $direction,
            'amount' => Money::toDecimal($amountMinor),
            'description' => $description,
            'source_type' => $policyId === null ? null : 'payroll_policy',
            'source_id' => $policyId,
            'is_automatic' => true,
        ]);
    }

    /**
     * Fold the adjustment rows of each branch into the allowance, bonus and
     * deduction totals of its allocation.
     *
     * @param  array<int, PayrollAllocation>  $allocations
     */
    private function applyManualAdjustmentTotals(Payroll $payroll, array $allocations): void
    {
        $rows = PayrollAdjustment::query()
            ->where('payroll_id', $payroll->getKey())
            ->get();

        foreach ($allocations as $branchId => $allocation) {
            $branchRows = $rows->where('branch_id', $branchId);

            $allowanceMinor = $this->sumOf($branchRows, fn (PayrollAdjustment $row): bool => $row->category === Category::Allowance);

            $bonusMinor = $this->sumOf($branchRows, fn (PayrollAdjustment $row): bool => $row->direction === Direction::Earning
                && $row->category !== Category::Allowance);

            $deductionMinor = $this->sumOf($branchRows, fn (PayrollAdjustment $row): bool => $row->direction === Direction::Deduction);

            $subtotalMinor = Money::toMinor($allocation->base_salary_amount)
                + Money::toMinor($allocation->shift_pay)
                + Money::toMinor($allocation->regular_commission_pay)
                + Money::toMinor($allocation->overtime_commission_pay)
                + $allowanceMinor
                + $bonusMinor
                - $deductionMinor;

            $allocation->forceFill([
                'allowance_total' => Money::toDecimal($allowanceMinor),
                'bonus_total' => Money::toDecimal($bonusMinor),
                'deduction_total' => Money::toDecimal($deductionMinor),
                'subtotal' => Money::toDecimal($subtotalMinor),
            ])->save();
        }

        PayrollAllocation::query()
            ->where('payroll_id', $payroll->getKey())
            ->whereNotIn('branch_id', array_keys($allocations))
            ->delete();
    }

    /**
     * @param  Collection<int, PayrollAdjustment>  $rows
     * @param  callable(PayrollAdjustment): bool  $matches
     */
    private function sumOf($rows, callable $matches): int
    {
        return $rows->filter($matches)->reduce(
            fn (int $carry, PayrollAdjustment $row): int => $carry + Money::toMinor($row->amount),
            0,
        );
    }

    /**
     * Roll the allocations up into the payroll header and apply the rounding
     * rule.
     *
     * Rounding happens exactly once, on the very last figure, after every
     * component has been added and subtracted at full precision. No individual
     * commission, KPI, allowance or penalty is ever rounded on its own.
     *
     * @param  array<int, PayrollAllocation>  $allocations
     * @param  array<string, mixed>  $attendance
     * @param  array<string, mixed>  $bill
     */
    private function finaliseTotals(
        Payroll $payroll,
        array $allocations,
        array $attendance,
        array $bill,
        ?PayrollPolicy $policy,
        int $baseSalaryMinor,
        array $paidLeave,
    ): void {
        $sum = fn (string $field): int => array_reduce(
            $allocations,
            fn (int $carry, PayrollAllocation $allocation): int => $carry + Money::toMinor($allocation->{$field}),
            0,
        );

        $calculatedMinor = $sum('subtotal') + $this->payrollLevelAdjustmentsMinor($payroll);

        $overrideMinor = Money::toMinor($payroll->approved_manual_adjustment);
        $unroundedMinor = max($calculatedMinor + $overrideMinor, 0);

        $rule = $policy?->rounding_rule ?? RoundingRule::CeilTo1000;
        $finalMinor = Money::ceilToStep($unroundedMinor, $rule->stepInDong());

        $payroll->forceFill([
            'base_salary' => Money::toDecimal($baseSalaryMinor),
            'calendar_days' => $paidLeave['calendar_days'],
            'required_work_days' => $paidLeave['required_work_days'],
            'paid_leave_days' => $paidLeave['paid_leave_days'],
            'unpaid_leave_days' => $paidLeave['unpaid_leave_days'],
            'daily_base_salary_rate' => Money::toDecimal($paidLeave['daily_base_salary_rate_minor']),
            'unpaid_leave_deduction' => Money::toDecimal($paidLeave['unpaid_leave_deduction_minor']),
            'worked_paid_leave_days' => $paidLeave['worked_paid_leave_days'],
            'worked_paid_leave_bonus_rate' => Money::toDecimal($paidLeave['worked_paid_leave_bonus_rate_minor']),
            'worked_paid_leave_bonus' => Money::toDecimal($paidLeave['worked_paid_leave_bonus_minor']),
            'net_base_salary' => Money::toDecimal($paidLeave['net_base_salary_minor']),
            'shift_count' => Money::toDecimal($sum('shift_count')),
            'shift_rate' => Money::toDecimal(Money::toMinor($this->compensation->profileFor((int) $payroll->employee_id, $payroll->paying_branch_id, $payroll->period_start)->shift_rate)),
            'shift_pay' => Money::toDecimal($sum('shift_pay')),
            'attendance_bonus' => Money::toDecimal($sum('attendance_bonus')),
            'regular_revenue' => Money::toDecimal($sum('regular_revenue')),
            'regular_commission_pay' => Money::toDecimal($sum('regular_commission_pay')),
            'overtime_revenue' => Money::toDecimal($sum('overtime_revenue')),
            'overtime_commission_pay' => Money::toDecimal($sum('overtime_commission_pay')),
            'commission_pay' => Money::toDecimal($sum('regular_commission_pay') + $sum('overtime_commission_pay')),
            'daily_kpi_bonus' => Money::toDecimal($sum('daily_kpi_bonus')),
            'bill_kpi_bonus' => Money::toDecimal($sum('bill_kpi_bonus')),
            'qualified_bill_count' => (int) $bill['actual'],
            'bill_kpi_achieved' => (bool) $bill['achieved'],
            'allowance_total' => Money::toDecimal($sum('allowance_total')),
            'bonus_total' => Money::toDecimal($sum('bonus_total')),
            'adjustment' => Money::toDecimal($sum('allowance_total') + $sum('bonus_total')),
            'deduction' => Money::toDecimal($sum('deduction_total')),
            'calculated_total' => Money::toDecimal($calculatedMinor),
            'unrounded_final_total' => Money::toDecimal($unroundedMinor),
            'rounding_adjustment' => Money::toDecimal($finalMinor - $unroundedMinor),
            'final_total' => Money::toDecimal($finalMinor),
            'rounding_rule' => $rule,
            // Legacy column kept in step with the real take-home figure so the
            // screens and exports written before rounding existed stay correct.
            'total' => Money::toDecimal($finalMinor),
        ])->save();
    }

    /** Adjustment rows that belong to the payroll as a whole, not to a branch. */
    private function payrollLevelAdjustmentsMinor(Payroll $payroll): int
    {
        return PayrollAdjustment::query()
            ->where('payroll_id', $payroll->getKey())
            ->whereNull('branch_id')
            ->get()
            ->reduce(
                fn (int $carry, PayrollAdjustment $row): int => $carry + ($row->direction->sign() * Money::toMinor($row->amount)),
                0,
            );
    }

    /**
     * Carry money owed from a closed period into this draft.
     *
     * A correction is claimed by the first draft payroll that recalculates
     * after it appears, and released again if that payroll is recalculated, so
     * it can never be counted twice or lost.
     *
     * @param  array<int, PayrollAllocation>  $allocations
     */
    private function applyPendingCorrections(Payroll $payroll, array $allocations): void
    {
        if (! $payroll->isEditable()) {
            return;
        }

        $corrections = PendingPayrollCorrection::query()
            ->where('employee_id', $payroll->employee_id)
            ->availableFor($payroll->getKey())
            ->get();

        foreach ($corrections as $correction) {
            $allocation = $allocations[$correction->branch_id] ?? null;

            PayrollAdjustment::query()->create([
                'payroll_id' => $payroll->getKey(),
                'payroll_allocation_id' => $allocation?->getKey(),
                'branch_id' => $allocation?->branch_id,
                'category' => Category::Correction,
                'direction' => $correction->direction,
                'amount' => $correction->amount,
                'description' => $correction->description,
                'source_type' => self::CORRECTION_SOURCE,
                'source_id' => $correction->getKey(),
                'is_automatic' => true,
            ]);

            $correction->forceFill([
                'applied_payroll_id' => $payroll->getKey(),
                'applied_at' => now(),
            ])->save();
        }
    }
}
