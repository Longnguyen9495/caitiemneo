<?php

namespace App\Actions\Risk;

use App\Actions\Invoices\RecalculateInvoiceAction;
use App\Enums\AuditAction;
use App\Enums\InvoiceStatus;
use App\Enums\RiskReviewStatus;
use App\Enums\WorkContext;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RiskFlag;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\CompensationResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Correct the specific, post-payment omission detected by the risk queue.
 *
 * Paid invoices normally stay immutable. This narrowly-scoped correction only
 * credits still-unassigned lines; prices, quantities, payment state and all
 * existing assignments remain untouched.
 */
class AssignMissingInvoiceEmployeeAction
{
    public function __construct(
        private RecalculateInvoiceAction $recalculate,
        private CompensationResolver $compensation,
        private AuditRecorder $auditor,
    ) {}

    public function handle(RiskFlag $riskFlag, User $employee, User $actor): Invoice
    {
        return DB::transaction(function () use ($riskFlag, $employee, $actor): Invoice {
            $lockedFlag = RiskFlag::query()->lockForUpdate()->findOrFail($riskFlag->getKey());

            if ($lockedFlag->rule !== 'invoice_line_without_employee' || ! $lockedFlag->isOpen()) {
                throw ValidationException::withMessages([
                    'risk_flag' => 'Cảnh báo này không còn có thể phân công nhanh.',
                ]);
            }

            $invoice = Invoice::query()
                ->whereKey($lockedFlag->subject_id)
                ->where('branch_id', $lockedFlag->branch_id)
                ->lockForUpdate()
                ->first();

            if ($invoice === null || $invoice->status !== InvoiceStatus::Paid) {
                throw ValidationException::withMessages([
                    'risk_flag' => 'Hóa đơn được cảnh báo không còn là hóa đơn đã thanh toán hợp lệ.',
                ]);
            }

            $isEligible = $employee->is_active && $employee->branchAssignments()
                ->where('branch_id', $invoice->branch_id)
                ->whereDate('starts_on', '<=', now()->toDateString())
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', now()->toDateString()))
                ->exists();

            if (! $isEligible) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Nhân viên này không còn làm việc tại chi nhánh của hóa đơn.',
                ]);
            }

            $invoice->load('items');
            $before = $invoice->auditSnapshot();
            $items = InvoiceItem::query()
                ->where('invoice_id', $invoice->getKey())
                ->whereNull('employee_id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'risk_flag' => 'Các dòng thiếu nhân viên đã được xử lý trước đó.',
                ]);
            }

            $referenceDate = $invoice->paid_at ?? $invoice->created_at ?? now();

            foreach ($items as $item) {
                $rate = $this->compensation->commissionRateFor(
                    $employee->getKey(),
                    $invoice->branch_id,
                    $item->service_id,
                    $item->work_context ?? WorkContext::Regular,
                    $referenceDate,
                );

                $item->forceFill([
                    'employee_id' => $employee->getKey(),
                    'commission_rate' => $rate['rate'],
                    'commission_rate_source' => $rate['source'],
                    'commission_rate_reason' => null,
                ])->save();
            }

            $result = $this->recalculate->handle($invoice);
            $result->load('items');

            $this->auditor->record(
                $result,
                $actor,
                AuditAction::Updated,
                $before,
                $result->auditSnapshot(),
                sprintf('Phân công nhanh %s cho %d dòng hóa đơn thiếu nhân viên từ cảnh báo rủi ro.', $employee->name, $items->count()),
            );

            $lockedFlag->forceFill([
                'review_status' => RiskReviewStatus::Accepted,
                'review_note' => sprintf('Đã phân công %s cho %d dòng thiếu nhân viên và tính lại hoa hồng.', $employee->name, $items->count()),
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();

            return $result;
        });
    }
}
