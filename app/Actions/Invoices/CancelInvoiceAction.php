<?php

namespace App\Actions\Invoices;

use App\Actions\Payrolls\RecordInvoiceReversalCorrectionAction;
use App\Enums\AuditAction;
use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\InvoiceStatus;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancel an invoice.
 *
 * A draft is simply marked cancelled. A paid invoice keeps its original income
 * row untouched for audit and gets a matching reversal expense instead, so the
 * cash book always shows both legs of what happened.
 */
class CancelInvoiceAction
{
    public function __construct(
        private RecordInvoiceReversalCorrectionAction $recordCorrection,
        private AuditRecorder $auditor,
    ) {}

    /** @throws ValidationException */
    public function handle(Invoice $invoice, User $actor, ?string $reason = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor, $reason): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($locked->status === InvoiceStatus::Cancelled) {
                return $locked;
            }

            $wasPaid = $locked->status === InvoiceStatus::Paid;
            $before = $locked->load('items')->auditSnapshot();

            $locked->forceFill([
                'status' => InvoiceStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason,
            ])->save();

            if ($wasPaid) {
                $this->recordReversal($locked, $actor, $reason);

                // Commission already counted in a closed payroll is clawed back
                // in the next period rather than by editing the old snapshot.
                $this->recordCorrection->handle($locked);
            }

            $this->auditor->record(
                $locked,
                $actor,
                AuditAction::Cancelled,
                $before,
                $locked->load('items')->auditSnapshot(),
                $reason,
            );

            return $locked;
        });
    }

    private function recordReversal(Invoice $invoice, User $actor, ?string $reason): void
    {
        $payment = $invoice->cashTransactions()
            ->where('idempotency_key', PayInvoiceAction::paymentKey($invoice))
            ->first();

        CashTransaction::query()->create([
            'branch_id' => $invoice->branch_id,
            'invoice_id' => $invoice->id,
            'reverses_transaction_id' => $payment?->id,
            'created_by' => $actor->id,
            'type' => CashTransactionType::Expense,
            'category' => CashTransactionCategory::Refund,
            'amount' => $payment?->amount ?? $invoice->total,
            'payment_method' => $invoice->payment_method,
            'reference' => $invoice->number,
            'idempotency_key' => self::reversalKey($invoice),
            'note' => trim('Đảo hóa đơn '.$invoice->number.'. '.($reason ?? '')),
            'occurred_at' => now(),
        ]);
    }

    public static function reversalKey(Invoice $invoice): string
    {
        return 'invoice-reversal:'.$invoice->getKey();
    }
}
