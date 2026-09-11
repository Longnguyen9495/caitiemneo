<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
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

        foreach ($invoice->items as $item) {
            $subtotalMinor += $this->recalculateItem($item);
        }

        $discountMinor = max(Money::toMinor($invoice->discount), 0);
        $totalMinor = max($subtotalMinor - $discountMinor, 0);

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
        $commissionMinor = Money::percentageOf($lineTotalMinor, $item->commission_rate);

        $item->forceFill([
            'line_total' => Money::toDecimal($lineTotalMinor),
            'commission_amount' => Money::toDecimal($commissionMinor),
        ])->save();

        return $lineTotalMinor;
    }
}
