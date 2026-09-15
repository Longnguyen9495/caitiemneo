<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Shifts\ConfigureEmployeeFixedShiftAction;
use App\Actions\Shifts\GenerateMonthlyFixedShiftScheduleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfigureEmployeeFixedShiftRequest;
use App\Http\Requests\Admin\GenerateMonthlyFixedShiftScheduleRequest;
use App\Models\Branch;
use App\Models\EmployeeFixedShift;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\BranchContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeShiftPlanController extends Controller
{
    public function __construct(private BranchContext $branchContext) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', EmployeeFixedShift::class);

        $month = $this->month($request);
        $branch = $this->branchContext->current();
        $managesPlans = $request->user()->can('create', EmployeeFixedShift::class)
            && $branch !== null;

        $fixedShifts = $branch === null ? collect() : EmployeeFixedShift::query()
            ->with(['employee:id,name', 'workShift:id,name,starts_at,ends_at'])
            ->where('branch_id', $branch->getKey())
            ->whereDate('effective_from', '<=', $month->endOfMonth())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $month))
            ->orderBy('employee_id')
            ->orderBy('effective_from')
            ->get();

        return view('admin.employee-shift-plans.index', [
            'branch' => $branch,
            'month' => $month,
            'managesPlans' => $managesPlans,
            'employees' => $managesPlans ? $this->employeesFor($branch, $month) : collect(),
            'shifts' => $managesPlans ? $this->shiftsFor($branch) : collect(),
            'fixedShifts' => $fixedShifts,
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

    public function generate(GenerateMonthlyFixedShiftScheduleRequest $request, GenerateMonthlyFixedShiftScheduleAction $generate): RedirectResponse
    {
        $data = $request->validated();
        $result = $generate->handle(
            $request->user(),
            Branch::query()->findOrFail($data['branch_id']),
            $data['month'],
        );

        return back()->with('success', "Đã tạo {$result['created']} ca từ lịch cố định; bỏ qua {$result['skipped']} ca đã có hoặc không còn hiệu lực.");
    }

    private function month(Request $request): CarbonImmutable
    {
        $raw = $request->string('month')->toString();

        return $raw === '' ? now()->toImmutable()->startOfMonth() : CarbonImmutable::parse($raw)->startOfMonth();
    }

    private function employeesFor(?Branch $branch, CarbonImmutable $month)
    {
        if ($branch === null) {
            return collect();
        }

        return User::query()
            ->active()
            ->staff()
            ->postedTo([$branch->getKey()], $month->toDateString(), $month->endOfMonth()->toDateString())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function shiftsFor(?Branch $branch)
    {
        if ($branch === null) {
            return collect();
        }

        return WorkShift::query()
            ->active()
            ->usableAt($branch->getKey())
            ->orderBy('starts_at')
            ->get(['id', 'name', 'starts_at', 'ends_at']);
    }
}
