<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\PayrollStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttendanceRecordRequest;
use App\Models\AttendanceRecord;
use App\Models\Payroll;
use App\Models\User;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(private BranchContext $branchContext) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        $month = Carbon::parse(($request->string('month')->toString() ?: now()->format('Y-m')).'-01')->startOfMonth();
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $branchIds = $this->branchContext->scopeIds() ?: [0];

        $records = AttendanceRecord::query()
            ->with(['employee', 'branch'])
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('work_date', [$from, $to])
            ->when($request->filled('employee_id'), fn ($query) => $query->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderByDesc('work_date')
            ->orderBy('shift_name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.attendance.index', [
            'records' => $records,
            'month' => $month,
            'summary' => $this->summary($from, $to, $branchIds),
            'employees' => $this->employees($from),
            'statuses' => AttendanceStatus::options(),
            'record' => new AttendanceRecord([
                'work_date' => now()->toDateString(),
                'shift_value' => 1,
                'status' => AttendanceStatus::Present,
            ]),
        ]);
    }

    public function store(AttendanceRecordRequest $request): RedirectResponse
    {
        AttendanceRecord::query()->create($request->validated());

        return back()->with('success', 'Đã ghi nhận chấm công.');
    }

    public function edit(AttendanceRecord $attendance): View
    {
        $this->authorize('update', $attendance);

        return view('admin.attendance.form', [
            'record' => $attendance,
            'employees' => $this->employees(),
            'statuses' => AttendanceStatus::options(),
        ]);
    }

    public function update(AttendanceRecordRequest $request, AttendanceRecord $attendance): RedirectResponse
    {
        $attendance->update($request->validated());

        return redirect()
            ->route('admin.attendance.index', ['month' => $attendance->work_date->format('Y-m')])
            ->with('success', 'Đã cập nhật chấm công.');
    }

    public function destroy(AttendanceRecord $attendance): RedirectResponse
    {
        $this->authorize('delete', $attendance);

        if ($this->isLockedByPayroll($attendance)) {
            return back()->withErrors([
                'work_date' => 'Ca này đã nằm trong một bảng lương đã chốt nên không thể xóa.',
            ]);
        }

        $attendance->delete();

        return back()->with('success', 'Đã xóa ca chấm công.');
    }

    /** A shift covered by a closed payroll must stay exactly as it was counted. */
    private function isLockedByPayroll(AttendanceRecord $attendance): bool
    {
        return Payroll::query()
            ->where('employee_id', $attendance->employee_id)
            ->whereIn('status', [PayrollStatus::Finalized->value, PayrollStatus::Paid->value])
            ->whereDate('period_start', '<=', $attendance->work_date->toDateString())
            ->whereDate('period_end', '>=', $attendance->work_date->toDateString())
            ->exists();
    }

    /** @return Collection<int, object> */
    private function summary(Carbon $from, Carbon $to, array $branchIds)
    {
        return AttendanceRecord::query()
            ->join('users', 'users.id', '=', 'attendance_records.employee_id')
            ->whereIn('attendance_records.branch_id', $branchIds)
            ->whereBetween('work_date', [$from, $to])
            ->selectRaw('users.name as employee_name')
            ->selectRaw('COUNT(*) as shift_rows')
            ->selectRaw('COALESCE(SUM(attendance_records.shift_value), 0) as shift_total')
            ->groupBy('employee_name')
            ->orderBy('employee_name')
            ->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, User> */
    private function employees(?Carbon $onDate = null)
    {
        $branchIds = $this->branchContext->scopeIds();

        // Only staff actually posted to the branch on that date may be clocked in.
        return User::query()
            ->active()
            ->when($branchIds !== [], fn ($query) => $query->whereHas('branchAssignments', fn ($assignment) => $assignment
                ->whereIn('branch_id', $branchIds)
                ->when($onDate !== null, fn ($inner) => $inner->covering($onDate->toDateString()))))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
