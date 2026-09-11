<?php

namespace App\Actions\Payrolls;

use App\Enums\PayrollAdjustmentDirection;
use App\Enums\PayrollStatus;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\PendingPayrollCorrection;
use App\Support\Money;

/**
 * Deals with commission that was paid on an invoice which is later cancelled.
 *
 * If the payroll covering the sale is still a draft, nothing is recorded: the
 * next recalculation simply stops counting the invoice. If that payroll has
 * already been finalised or paid, the snapshot must not move, so the clawback
 * is parked as a pending correction and the employee's next draft payroll picks
 * it up as a visible line.
 *
 * The pending row is keyed on the invoice, so replaying the same cancellation
 * can never create a second clawback.
 */
class RecordInvoiceReversalCorrectionAction
{
    public function handle(Invoice $invoice): void
    {
        $invoice->loadMissing('items');

        if ($invoice->paid_at === null) {
            return;
        }

        $byEmployee = $invoice->items
            ->whereNotNull('employee_id')
            ->groupBy('employee_id');

        foreach ($byEmployee as $employeeId => $items) {
            $commissionMinor = $items->reduce(
                fn (int $carry, $item): int => $carry + Money::toMinor($item->commission_amount),
                0,
            );

            if ($commissionMinor <= 0) {
                continue;
            }

            $closedPayroll = $this->closedPayrollCovering((int) $employeeId, $invoice);

            if ($closedPayroll === null) {
                continue;
            }

            PendingPayrollCorrection::query()->updateOrCreate(
                [
                    'source_type' => 'invoice_reversal',
                    'source_id' => $invoice->getKey(),
                    'employee_id' => (int) $employeeId,
                ],
                [
                    'branch_id' => $invoice->branch_id,
                    'direction' => PayrollAdjustmentDirection::Deduction,
                    'amount' => Money::toDecimal($commissionMinor),
                    'description' => 'Thu hồi hoa hồng hóa đơn '.$invoice->number.' đã hủy sau khi chốt lương',
                    'origin_payroll_id' => $closedPayroll->getKey(),
                ],
            );
        }
    }

    /** The already closed payroll whose period contains the sale, if any. */
    private function closedPayrollCovering(int $employeeId, Invoice $invoice): ?Payroll
    {
        return Payroll::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [PayrollStatus::Finalized->value, PayrollStatus::Paid->value])
            ->whereDate('period_start', '<=', $invoice->paid_at->toDateString())
            ->whereDate('period_end', '>=', $invoice->paid_at->toDateString())
            ->first();
    }
}
