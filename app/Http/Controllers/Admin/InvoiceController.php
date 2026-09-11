<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Invoices\UpdateInvoiceAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateInvoiceRequest;
use App\Models\AuditEvent;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use App\Queries\InvoiceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request, InvoiceQuery $invoices): View
    {
        $this->authorize('viewAny', Invoice::class);

        return view('admin.invoices.index', [
            'invoices' => $invoices->build($request)
                ->with(['creator', 'customer', 'branch'])
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'statuses' => InvoiceStatus::options(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function edit(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load([
            'appointment',
            'items.employee',
            'items.service',
            'creator',
            'canceller',
            'billKpiVerifier',
            'branch',
            'cashTransactions.creator',
        ]);

        $user = request()->user();

        return view('admin.invoices.edit', [
            'invoice' => $invoice,
            'services' => Service::query()->active()->inMenuOrder()->get(),
            // The picker offers only staff posted to this branch; the rule in
            // UpdateInvoiceRequest is what actually enforces it on submit.
            'employees' => User::query()
                ->active()
                ->staff()
                ->whereHas('branchAssignments', fn ($query) => $query
                    ->where('branch_id', $invoice->branch_id)
                    ->covering($invoice->created_at ?? now()))
                ->orderBy('name')
                ->get(['id', 'name']),
            'paymentMethods' => PaymentMethod::options(),
            // The trail is management information, not an operator's tool.
            'auditEvents' => $user?->isLeadership()
                ? AuditEvent::query()->forSubject($invoice)->with('actor')->get()
                : null,
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice, UpdateInvoiceAction $updateInvoice): RedirectResponse
    {
        $updateInvoice->handle($invoice, $request->validated(), $request->user());

        return redirect()
            ->route('admin.invoices.edit', $invoice)
            ->with('success', 'Đã lưu hóa đơn.');
    }

    /**
     * Mark, or unmark, an invoice as counting towards the employee bill KPI.
     *
     * Verification is a management judgement, so the person and the moment are
     * recorded alongside the flag.
     */
    public function verifyBillKpi(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('verifyBillKpi', $invoice);

        $validated = $request->validate([
            'qualified_for_bill_kpi' => ['required', 'boolean'],
            'bill_kpi_note' => ['nullable', 'string', 'max:255'],
        ], [], [
            'qualified_for_bill_kpi' => 'trạng thái KPI',
            'bill_kpi_note' => 'ghi chú',
        ]);

        $qualified = (bool) $validated['qualified_for_bill_kpi'];

        $invoice->forceFill([
            'qualified_for_bill_kpi' => $qualified,
            'bill_kpi_verified_by' => $qualified ? $request->user()->getKey() : null,
            'bill_kpi_verified_at' => $qualified ? now() : null,
            'bill_kpi_note' => $validated['bill_kpi_note'] ?? null,
        ])->save();

        return back()->with('success', $qualified
            ? 'Đã xác nhận hóa đơn hợp lệ cho KPI số bill.'
            : 'Đã bỏ xác nhận KPI số bill cho hóa đơn này.');
    }
}
