<?php

namespace App\Actions\Invoices;

use App\Enums\AuditAction;
use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Settle a draft invoice and mirror it into the cash book.
 *
 * Atomicity and idempotency are guaranteed three ways: the invoice row is locked
 * for the whole transaction, a second call sees the `paid` status and returns
 * early, and the generated cash transaction carries a unique idempotency key so
 * even a lost lock cannot produce two income rows.
 */
class PayInvoiceAction
{
    public function __construct(
        private RecalculateInvoiceAction $recalculate,
        private AuditRecorder $auditor,
    ) {}

    /** @throws ValidationException */
    public function handle(Invoice $invoice, User $actor, PaymentMethod $paymentMethod, ?CarbonInterface $paidAt = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor, $paymentMethod, $paidAt): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($locked->status === InvoiceStatus::Paid) {
                return $locked;
            }

            if ($locked->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Hóa đơn đã hủy không thể thanh toán.',
                ]);
            }

            $this->recalculate->handle($locked);

            $before = $locked->load('items')->auditSnapshot();
            $settledAt = $paidAt ?? now();

            $locked->forceFill([
                'status' => InvoiceStatus::Paid,
                'payment_method' => $paymentMethod,
                'paid_at' => $settledAt,
            ])->save();

            CashTransaction::query()->create([
                'branch_id' => $locked->branch_id,
                'invoice_id' => $locked->id,
                'created_by' => $actor->id,
                'type' => CashTransactionType::Income,
                'category' => CashTransactionCategory::ServiceRevenue,
                'amount' => $locked->total,
                'payment_method' => $paymentMethod,
                'reference' => $locked->number,
                'idempotency_key' => self::paymentKey($locked),
                'note' => 'Thu tiền hóa đơn '.$locked->number,
                'occurred_at' => $settledAt,
            ]);

            $this->auditor->record(
                $locked,
                $actor,
                AuditAction::Paid,
                $before,
                $locked->load('items')->auditSnapshot(),
            );

            return $locked;
        });
    }

    public static function paymentKey(Invoice $invoice): string
    {
        return 'invoice-payment:'.$invoice->getKey();
    }
}
