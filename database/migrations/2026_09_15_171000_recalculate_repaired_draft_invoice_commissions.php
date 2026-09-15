<?php

use App\Actions\Invoices\RecalculateInvoiceAction;
use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Recalculate editable invoices whose rate was repaired by the preceding
     * backfill. This uses the same weighted allocation as normal invoice edits,
     * including discounts and minor-unit rounding.
     */
    public function up(): void
    {
        $invoiceIds = DB::table('invoices')
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', 'draft')
            ->where('invoices.commission_rate', '>', 0)
            ->where('invoice_items.commission_amount', 0)
            ->distinct()
            ->pluck('invoices.id');

        $recalculate = app(RecalculateInvoiceAction::class);

        foreach ($invoiceIds as $invoiceId) {
            $invoice = Invoice::query()->with('items')->find($invoiceId);

            if ($invoice !== null) {
                $recalculate->handle($invoice);
            }
        }
    }

    public function down(): void
    {
        // Calculated values on editable invoices must not be removed on rollback.
    }
};
