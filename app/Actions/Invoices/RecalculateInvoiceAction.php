<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\Allocator;
use App\Support\Money;

/**
 * Recompute every derived money field of an invoice from its stored lines.
 *
 * The client never supplies totals: line totals, commissions, the subtotal and
 * the grand total are always derived here on integer minor units.
 */
class RecalculateInvoiceAction
{
    public function handle(Invoice $invoice): Invoice
    {
        $invoice->load('items');

        $subtotalMinor = 0;
        $lineTotals = [];

        foreach ($invoice->items as $item) {
            $lineTotals[$item->getKey()] = $this->recalculateItem($item);
            $subtotalMinor += $lineTotals[$item->getKey()];
        }

        $discountMinor = max(Money::toMinor($invoice->discount), 0);
        $totalMinor = max($subtotalMinor - $discountMinor, 0);
        $commissionMinor = Money::percentageOf($totalMinor, $invoice->commission_rate);
        $commissionShares = Allocator::distribute($commissionMinor, $lineTotals);

        foreach ($invoice->items as $item) {
            $item->forceFill([
                'employee_id' => $invoice->employee_id,
                'commission_rate' => $invoice->commission_rate,
                'commission_rate_source' => $invoice->commission_rate_source,
                'commission_amount' => Money::toDecimal($commissionShares[$item->getKey()] ?? 0),
            ])->save();
        }

        $invoice->forceFill([
            'subtotal' => Money::toDecimal($subtotalMinor),
            'discount' => Money::toDecimal($discountMinor),
            'total' => Money::toDecimal($totalMinor),
        ])->save();

        return $invoice;
    }

    /** @return int the line total in minor units */
    private function recalculateItem(InvoiceItem $item): int
    {
        $lineTotalMinor = Money::multiplyByQuantity(Money::toMinor($item->unit_price), $item->quantity);

        $item->forceFill([
            'line_total' => Money::toDecimal($lineTotalMinor),
        ])->save();

        return $lineTotalMinor;
    }
}
