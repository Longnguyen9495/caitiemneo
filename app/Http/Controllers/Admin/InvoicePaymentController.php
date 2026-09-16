<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Invoices\CancelInvoiceAction;
use App\Actions\Invoices\PayInvoiceAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelInvoiceRequest;
use App\Http\Requests\Admin\PayInvoiceRequest;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoicePaymentController extends Controller
{
    public function store(PayInvoiceRequest $request, Invoice $invoice, PayInvoiceAction $payInvoice): RedirectResponse
    {
        $validated = $request->validated();
        $proofDirectory = 'invoice-payment-proofs/'.$invoice->number;

        $payInvoice->handle(
            $invoice,
            $request->user(),
            PaymentMethod::from($validated['payment_method']),
            billImagePath: $request->file('payment_proof_image')->store($proofDirectory, 'local'),
        );

        return redirect()
            ->route('admin.invoices.edit', $invoice)
            ->with('success', 'Đã ghi nhận thanh toán kèm chứng từ và tạo khoản thu tương ứng.');
    }

    public function proof(Invoice $invoice): StreamedResponse
    {
        $this->authorize('view', $invoice);

        abort_unless(
            filled($invoice->bill_image_path) && Storage::disk('local')->exists($invoice->bill_image_path),
            404,
        );

        return Storage::disk('local')->response($invoice->bill_image_path);
    }

    public function destroy(CancelInvoiceRequest $request, Invoice $invoice, CancelInvoiceAction $cancelInvoice): RedirectResponse
    {
        // Hủy hóa đơn nháp là việc thường ngày ở quầy. Hủy hóa đơn **đã thu
        // tiền** là đảo lại tiền thật, nên chỗ này mới hỏi lại mật khẩu.
        // Middleware không phân biệt được hai trường hợp nên phải kiểm tra ở đây.
        if ($invoice->status === InvoiceStatus::Paid) {
            $this->requirePasswordConfirmation($request);
        }

        $cancelInvoice->handle($invoice, $request->user(), $request->validated('cancel_reason'));

        return redirect()
            ->route('admin.invoices.edit', $invoice)
            ->with('success', 'Đã hủy hóa đơn. Giao dịch gốc được giữ lại và ghi nhận bút toán đảo.');
    }
}
