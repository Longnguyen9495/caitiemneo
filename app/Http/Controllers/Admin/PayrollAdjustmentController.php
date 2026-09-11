<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Payrolls\CalculatePayrollAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PayrollAdjustmentRequest;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Hand-entered payroll lines: allowances, bonuses, penalties, advances and
 * corrections carried over from a closed period.
 */
class PayrollAdjustmentController extends Controller
{
    public function store(PayrollAdjustmentRequest $request, Payroll $payroll, CalculatePayrollAction $calculate): RedirectResponse
    {
        $data = $request->validated();
        $allocation = $payroll->allocations()->where('branch_id', $data['branch_id'] ?? null)->first();

        PayrollAdjustment::query()->create([
            'payroll_id' => $payroll->getKey(),
            'payroll_allocation_id' => $allocation?->getKey(),
            'branch_id' => $allocation?->branch_id,
            'category' => $data['category'],
            'direction' => $data['direction'],
            'amount' => $data['amount'],
            'description' => $data['description'],
            'is_automatic' => false,
            'created_by' => $request->user()->getKey(),
            'approved_by' => $request->user()->isOwner() ? $request->user()->getKey() : null,
            'approved_at' => $request->user()->isOwner() ? now() : null,
        ]);

        $calculate->refresh($payroll);

        return redirect()->route('admin.payrolls.show', $payroll)->with('success', 'Đã thêm khoản điều chỉnh.');
    }

    public function destroy(Request $request, Payroll $payroll, PayrollAdjustment $adjustment, CalculatePayrollAction $calculate): RedirectResponse
    {
        $this->authorize('update', $payroll);

        abort_unless($adjustment->payroll_id === $payroll->getKey(), 404);

        if ($adjustment->is_automatic) {
            return back()->withErrors([
                'category' => 'Khoản do hệ thống tự tính không thể xóa thủ công; hãy sửa chính sách hoặc dữ liệu nguồn.',
            ]);
        }

        $adjustment->delete();
        $calculate->refresh($payroll);

        return redirect()->route('admin.payrolls.show', $payroll)->with('success', 'Đã xóa khoản điều chỉnh.');
    }
}
