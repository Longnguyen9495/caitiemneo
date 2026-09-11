<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Invoices\CancelInvoiceAction;
use App\Actions\Invoices\PayInvoiceAction;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelInvoiceRequest;
use App\Http\Requests\Admin\PayInvoiceRequest;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;

class InvoicePaymentController extends Controller
{
    public function store(PayInvoiceRequest $request, Invoice $invoice, PayInvoiceAction $payInvoice): RedirectResponse
    {
        $payInvoice->handle(
            $invoice,
            $request->user(),
            PaymentMethod::from($request->validated('payment_method')),
        );

        return redirect()
            ->route('admin.invoices.edit', $invoice)
            ->with('success', 'Đã ghi nhận thanh toán và tạo khoản thu tương ứng.');
    }

    public function destroy(CancelInvoiceRequest $request, Invoice $invoice, CancelInvoiceAction $cancelInvoice): RedirectResponse
    {
        $cancelInvoice->handle($invoice, $request->user(), $request->validated('cancel_reason'));

        return redirect()
            ->route('admin.invoices.edit', $invoice)
            ->with('success', 'Đã hủy hóa đơn. Giao dịch gốc được giữ lại và ghi nhận bút toán đảo.');
    }
}
