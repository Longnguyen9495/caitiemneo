<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Shifts\ConfigureEmployeeFixedShiftAction;
use App\Actions\Shifts\GenerateMonthlyFixedShiftScheduleAction;
use App\Http\Controllers\Concerns\ReadsPeriodFromRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfigureEmployeeFixedShiftRequest;
use App\Http\Requests\Admin\EndEmployeeFixedShiftRequest;
use App\Http\Requests\Admin\GenerateMonthlyFixedShiftScheduleRequest;
use App\Models\Branch;
use App\Models\EmployeeFixedShift;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Shifts\ShiftPlanningOptions;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeShiftPlanController extends Controller
{
    use ReadsPeriodFromRequest;

    public function __construct(
        private BranchContext $branchContext,
        private ShiftPlanningOptions $options,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', EmployeeFixedShift::class);

        $month = $this->requestedMonthStart($request);
        $branch = $this->branchContext->current();
        $managesPlans = $branch !== null && $request->user()->can('create', EmployeeFixedShift::class);

        if ($branch === null) {
            return view('admin.employee-shift-plans.index', [
                'branch' => null,
                'month' => $month,
                'managesPlans' => false,
                'employees' => collect(),
                'shifts' => collect(),
                'fixedShifts' => collect(),
            ]);
        }

        return view('admin.employee-shift-plans.index', [
            'branch' => $branch,
            'month' => $month,
            'managesPlans' => $managesPlans,
            'employees' => $managesPlans
                ? $this->options->staff([$branch->getKey()], $month->toDateString(), $month->endOfMonth()->toDateString())
                : collect(),
            'shifts' => $managesPlans ? $this->options->shifts([$branch->getKey()]) : collect(),
            'fixedShifts' => EmployeeFixedShift::query()
                ->with(['employee:id,name', 'workShift:id,name,starts_at,ends_at'])
                ->where('branch_id', $branch->getKey())
                ->effectiveBetween($month->toDateString(), $month->endOfMonth()->toDateString())
                ->orderBy('employee_id')
                ->orderBy('effective_from')
                ->get(),
        ]);
    }

    public function storeFixedShift(ConfigureEmployeeFixedShiftRequest $request, ConfigureEmployeeFixedShiftAction $configure): RedirectResponse
    {
        $data = $request->validated();

        $configure->handle(
            $request->user(),
            User::query()->findOrFail($data['employee_id']),
            Branch::query()->findOrFail($data['branch_id']),
            WorkShift::query()->findOrFail($data['work_shift_id']),
            $data['effective_from'],
            $data['effective_to'] ?? null,
        );

        return back()->with('success', 'Đã cấu hình ca cố định cho nhân viên.');
    }

    /**
     * Đóng một ca cố định lại từ một ngày nhất định.
     *
     * Không xóa, vì chính nó giải thích tại sao lịch tháng trước trông như vậy.
     */
    public function endFixedShift(EndEmployeeFixedShiftRequest $request, EmployeeFixedShift $fixedShift, ConfigureEmployeeFixedShiftAction $configure): RedirectResponse
    {
        $configure->end($request->user(), $fixedShift, $request->validated('effective_to'));

        return back()->with('success', 'Đã kết thúc ca cố định.');
    }

    /** Chỉ ca cố định chưa tới ngày hiệu lực mới xóa hẳn được. */
    public function destroyFixedShift(Request $request, EmployeeFixedShift $fixedShift, ConfigureEmployeeFixedShiftAction $configure): RedirectResponse
    {
        $this->authorize('delete', $fixedShift);
        $configure->remove($request->user(), $fixedShift);

        return back()->with('success', 'Đã xóa ca cố định chưa hiệu lực.');
    }

    public function generate(GenerateMonthlyFixedShiftScheduleRequest $request, GenerateMonthlyFixedShiftScheduleAction $generate): RedirectResponse
    {
        $data = $request->validated();
        $result = $generate->handle(
            $request->user(),
            Branch::query()->findOrFail($data['branch_id']),
            $data['month'],
        );

        $message = "Đã tạo {$result['created']} ca từ lịch cố định; bỏ qua {$result['skipped']} ca đã có hoặc không còn hiệu lực.";

        // Một ca cố định hỏng không được phép nuốt mất kết quả của những ca
        // còn lại: báo cả số đã tạo lẫn lý do của phần không tạo được.
        if ($result['failed'] > 0) {
            return back()->with('error', $message." Còn {$result['failed']} ca không tạo được: ".implode(' ', $result['reasons']));
        }

        return back()->with('success', $message);
    }
}
