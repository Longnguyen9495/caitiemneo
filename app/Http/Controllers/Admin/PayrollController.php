<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Payrolls\CalculatePayrollAction;
use App\Actions\Payrolls\SavePayrollAction;
use App\Enums\PayrollAdjustmentCategory;
use App\Enums\PayrollAdjustmentDirection;
use App\Enums\PayrollStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePayrollRequest;
use App\Http\Requests\Admin\UpdatePayrollRequest;
use App\Models\Payroll;
use App\Models\User;
use App\Queries\PayrollQuery;
use App\Services\Calendar\PayrollCalendarQuery;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function __construct(
        private BranchContext $branchContext,
        private PayrollCalendarQuery $calendarQuery,
    ) {}

    public function index(Request $request, PayrollQuery $payrolls): View
    {
        $this->authorize('viewAny', Payroll::class);

        return view('admin.payrolls.index', [
            'payrolls' => $payrolls->build($request, $request->user())
                ->with(['employee', 'finalizer', 'allocations.branch'])
                ->orderByDesc('period_start')
                ->orderBy('employee_id')
                ->paginate(20)
                ->withQueryString(),
            'employees' => $this->employees(),
            'statuses' => PayrollStatus::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Payroll::class);

        return view('admin.payrolls.form', [
            'employees' => $this->employees(),
            'branches' => $this->branchContext->available(),
            'periodStart' => now()->startOfMonth(),
            'periodEnd' => now()->endOfMonth(),
        ]);
    }

    public function store(StorePayrollRequest $request, SavePayrollAction $savePayroll): RedirectResponse
    {
        $payroll = $savePayroll->create($request->validated(), $request->user());

        return redirect()->route('admin.payrolls.show', $payroll)->with('success', 'Đã tạo bảng lương nháp.');
    }

    public function show(Payroll $payroll): View
    {
        $this->authorize('view', $payroll);

        $payroll->load([
            'employee',
            'creator',
            'finalizer',
            'approver',
            'policy',
            'payingBranch',
            'allocations.branch',
            'adjustments.branch',
            'dailyKpiResults.branch',
            'dailyKpiResults.tier',
            'cashTransactions',
        ]);

        $calendar = $this->calendarQuery->forPayroll(
            $payroll,
            route('admin.payrolls.show', $payroll),
        );

        return view('admin.payrolls.show', [
            'payroll' => $payroll,
            'calendar' => $calendar,
            'adjustmentCategories' => PayrollAdjustmentCategory::manualOptions(),
            'adjustmentDirections' => PayrollAdjustmentDirection::options(),
        ]);
    }

    public function update(UpdatePayrollRequest $request, Payroll $payroll, SavePayrollAction $savePayroll): RedirectResponse
    {
        $savePayroll->update($payroll, $request->validated(), $request->user());

        return redirect()->route('admin.payrolls.show', $payroll)->with('success', 'Đã cập nhật và tính lại bảng lương.');
    }

    public function recalculate(Payroll $payroll, CalculatePayrollAction $calculate): RedirectResponse
    {
        $this->authorize('update', $payroll);

        $calculate->refresh($payroll);

        return redirect()->route('admin.payrolls.show', $payroll)->with('success', 'Đã tính lại bảng lương theo dữ liệu mới nhất.');
    }

    /** @return Collection<int, User> */
    private function employees()
    {
        return User::query()
            ->active()
            ->postedTo($this->branchContext->scopeIds())
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
