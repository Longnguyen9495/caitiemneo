<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Payrolls\CalculatePayrollAction;
use App\Actions\Payrolls\CancelPayrollAction;
use App\Actions\Payrolls\FinalizePayrollAction;
use App\Actions\Payrolls\PayPayrollAction;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PayPayrollRequest;
use App\Models\Payroll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayrollStatusController extends Controller
{
    public function finalize(Request $request, Payroll $payroll, FinalizePayrollAction $finalize): RedirectResponse
    {
        $this->authorize('finalize', $payroll);

        $finalize->handle($payroll, $request->user());

        return redirect()->route('admin.payrolls.show', $payroll)->with('success', 'Đã chốt bảng lương.');
    }

    public function pay(PayPayrollRequest $request, Payroll $payroll, PayPayrollAction $pay): RedirectResponse
    {
        $pay->handle($payroll, $request->user(), PaymentMethod::from($request->validated('payment_method')));

        return redirect()->route('admin.payrolls.show', $payroll)
            ->with('success', 'Đã chi trả lương và ghi nhận khoản chi tương ứng.');
    }

    public function cancel(Request $request, Payroll $payroll, CancelPayrollAction $cancel): RedirectResponse
    {
        $this->authorize('cancel', $payroll);

        $validated = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:255'],
        ], [], ['cancel_reason' => 'lý do hủy']);

        $cancel->handle($payroll, $request->user(), $validated['cancel_reason']);

        return redirect()->route('admin.payrolls.show', $payroll)->with('success', 'Đã hủy bảng lương.');
    }

    /**
     * Record an owner-approved difference between the calculated figure and the
     * amount actually paid.
     *
     * The override is stored as its own audited field with a reason and an
     * approver; the calculated total is never overwritten, and the client can
     * never post a final total or a rounding amount directly.
     */
    public function approveVariance(Request $request, Payroll $payroll, CalculatePayrollAction $calculate): RedirectResponse
    {
        $this->authorize('approveVariance', $payroll);

        if (! $payroll->isEditable()) {
            return back()->withErrors(['status' => 'Chỉ bảng lương nháp mới được điều chỉnh tổng.']);
        }

        $validated = $request->validate([
            'approved_manual_adjustment' => ['required', 'numeric', 'min:-99999999999', 'max:99999999999'],
            'variance_reason' => ['required', 'string', 'max:255'],
        ], [], [
            'approved_manual_adjustment' => 'số tiền điều chỉnh',
            'variance_reason' => 'lý do điều chỉnh',
        ]);

        $payroll->forceFill([
            'approved_manual_adjustment' => $validated['approved_manual_adjustment'],
            'variance_reason' => $validated['variance_reason'],
            'approved_by' => $request->user()->getKey(),
            'approved_at' => now(),
        ])->save();

        $calculate->refresh($payroll);

        return redirect()->route('admin.payrolls.show', $payroll)
            ->with('success', 'Đã ghi nhận điều chỉnh tổng lương kèm lý do và người duyệt.');
    }
}
