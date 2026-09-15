<?php

namespace App\Actions\Invoices;

use App\Enums\AuditAction;
use App\Enums\WorkContext;
use App\Models\BranchService;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\CompensationResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Persist the editable part of a draft invoice: customer snapshot, discount,
 * payment method, note and the service lines.
 *
 * The branch of an invoice is never taken from the request; it is fixed when
 * the invoice is created and stays put.
 */
class UpdateInvoiceAction
{
    public function __construct(
        private RecalculateInvoiceAction $recalculate,
        private CompensationResolver $compensation,
        private AuditRecorder $auditor,
    ) {}

    /**
     * @param  array{employee_id?: int|null, customer_name?: string|null, customer_phone?: string|null, discount?: mixed, payment_method?: string|null, note?: string|null, items?: array<int, array<string, mixed>>}  $data
     *
     * @throws ValidationException when the invoice is no longer a draft
     */
    public function handle(Invoice $invoice, array $data, ?User $actor = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $actor): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if (! $locked->isEditable()) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ hóa đơn ở trạng thái nháp mới được chỉnh sửa.',
                ]);
            }

            // Snapshotted before anything moves, so the trail shows the state
            // an operator actually changed away from — lines included.
            $before = $locked->load('items')->auditSnapshot();
            $employeeId = ($data['employee_id'] ?? null) ?: null;
            $referenceDate = $locked->created_at ?? now();
            $rate = $this->compensation->commissionRateFor(
                $employeeId,
                $locked->branch_id,
                null,
                WorkContext::Regular,
                $referenceDate,
            );

            $locked->forceFill([
                'employee_id' => $employeeId,
                'commission_rate' => $rate['rate'],
                'commission_rate_source' => $rate['source'],
                'customer_name' => $data['customer_name'] ?? $locked->customer_name,
                'customer_phone' => $data['customer_phone'] ?? $locked->customer_phone,
                'discount' => $data['discount'] ?? $locked->discount,
                'payment_method' => ($data['payment_method'] ?? null) ?: null,
                'note' => $data['note'] ?? null,
            ])->save();

            if (array_key_exists('items', $data)) {
                $this->syncItems($locked, $data['items'] ?? [], $actor);
            }

            $result = $this->recalculate->handle($locked);

            $this->auditor->record(
                $result,
                $actor,
                AuditAction::Updated,
                $before,
                $result->load('items')->auditSnapshot(),
                $this->overrideReasons($data['items'] ?? []),
            );

            return $result;
        });
    }

    /**
     * The reasons given for pricing lines outside the branch range.
     *
     * Collected onto the audit event so a reviewer reading the timeline sees
     * why the price moved without having to diff every line by hand.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function overrideReasons(array $items): ?string
    {
        $reasons = [];

        foreach ($items as $row) {
            $reason = trim((string) ($row['price_override_reason'] ?? ''));

            if ($reason !== '') {
                $reasons[] = $reason;
            }
        }

        return $reasons === [] ? null : implode(' | ', array_unique($reasons));
    }

    /**
     * Replace the invoice lines with the submitted ones.
     *
     * Only the raw inputs are trusted; `line_total` and `commission_amount` are
     * always derived by {@see RecalculateInvoiceAction}. The employee and its
     * compensation profile belong to the invoice as a whole, never to one line.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Invoice $invoice, array $items, ?User $actor): void
    {
        $keptIds = [];
        $branchServices = BranchService::query()
            ->with('service')
            ->where('branch_id', $invoice->branch_id)
            ->whereIn('service_id', array_filter(array_column($items, 'service_id')))
            ->get()
            ->keyBy('service_id');

        $mayApproveOvertime = $actor !== null && $actor->can('approveOvertime', $invoice);
        foreach ($items as $row) {
            $serviceId = ($row['service_id'] ?? null) ?: null;
            $branchService = $serviceId ? $branchServices->get((int) $serviceId) : null;

            $existing = ($row['id'] ?? null)
                ? $invoice->items()->whereKey($row['id'])->first()
                : null;

            $context = $this->resolveWorkContext($row, $existing, $mayApproveOvertime);

            $attributes = [
                'invoice_id' => $invoice->id,
                'service_id' => $branchService?->service_id,
                // Keep a line-level snapshot for existing payroll/report queries.
                'employee_id' => $invoice->employee_id,
                'work_context' => $context,
                'name' => trim((string) ($row['name'] ?? '')) ?: ($branchService?->service?->name ?? 'Dịch vụ'),
                'quantity' => $row['quantity'] ?? 1,
                'unit_price' => $row['unit_price'] ?? 0,
                'commission_rate' => $invoice->commission_rate,
                'commission_rate_source' => $invoice->commission_rate_source,
                'commission_rate_reason' => null,
                'line_total' => 0,
                'commission_amount' => 0,
            ];

            if ($context === WorkContext::Overtime && $mayApproveOvertime) {
                $attributes['overtime_approved_by'] = $actor?->getKey();
                $attributes['overtime_approved_at'] = $existing?->overtime_approved_at ?? now();
            }

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $keptIds[] = $existing->getKey();

                continue;
            }

            $keptIds[] = InvoiceItem::query()->create($attributes)->getKey();
        }

        $invoice->items()->whereKeyNot($keptIds)->delete();
    }

    /**
     * Out-of-hours status is a claim about how the work was done, so it needs a
     * manager's approval; an unprivileged edit keeps whatever was approved
     * before and otherwise falls back to normal hours.
     *
     * @param  array<string, mixed>  $row
     */
    private function resolveWorkContext(array $row, ?InvoiceItem $existing, bool $mayApproveOvertime): WorkContext
    {
        $requested = WorkContext::tryFrom((string) ($row['work_context'] ?? '')) ?? WorkContext::Regular;

        if ($mayApproveOvertime) {
            return $requested;
        }

        return $existing?->work_context ?? WorkContext::Regular;
    }
}
