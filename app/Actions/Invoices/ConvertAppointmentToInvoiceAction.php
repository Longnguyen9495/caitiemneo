<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\WorkContext;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\CompensationResolver;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Turn a finished appointment into a draft invoice.
 *
 * The operation is idempotent: `appointments.id` is unique on `invoices`, and the
 * appointment row is locked for the duration of the transaction, so a double
 * submit returns the existing invoice instead of creating a second one.
 *
 * The branch is inherited from the appointment and never taken from the caller.
 */
class ConvertAppointmentToInvoiceAction
{
    public function __construct(
        private RecalculateInvoiceAction $recalculate,
        private CompensationResolver $compensation,
    ) {}

    public function handle(Appointment $appointment, User $actor): Invoice
    {
        return DB::transaction(function () use ($appointment, $actor): Invoice {
            $locked = Appointment::query()
                ->lockForUpdate()
                ->with(['services.service', 'employee', 'branch'])
                ->findOrFail($appointment->getKey());

            $existing = $locked->invoice()->first();

            if ($existing !== null) {
                return $existing;
            }

            $commission = $this->compensation->commissionRateFor(
                $locked->employee_id,
                $locked->branch_id,
                null,
                WorkContext::Regular,
                $locked->starts_at,
            );

            $invoice = Invoice::query()->create([
                'branch_id' => $locked->branch_id,
                'number' => DocumentNumber::forInvoice($locked->branch?->code),
                'appointment_id' => $locked->id,
                'customer_id' => $locked->customer_id,
                'employee_id' => $locked->employee_id,
                'created_by' => $actor->id,
                'customer_name' => $locked->customer_name,
                'customer_phone' => $locked->customer_phone,
                'status' => InvoiceStatus::Draft,
                'commission_rate' => $commission['rate'],
                'commission_rate_source' => $commission['source'],
                'note' => $locked->note,
            ]);

            foreach ($locked->services as $appointmentService) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'service_id' => $appointmentService->service_id,
                    'employee_id' => $locked->employee_id,
                    'work_context' => WorkContext::Regular,
                    'name' => $appointmentService->service->name,
                    'quantity' => 1,
                    'unit_price' => $appointmentService->price,
                    'line_total' => $appointmentService->price,
                    'commission_rate' => $invoice->commission_rate,
                    'commission_rate_source' => $invoice->commission_rate_source,
                    'commission_amount' => 0,
                ]);
            }

            return $this->recalculate->handle($invoice);
        });
    }
}
