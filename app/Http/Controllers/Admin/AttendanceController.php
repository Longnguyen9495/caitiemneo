<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Attendance\SaveManualAttendanceAction;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\StaffAttendanceController;
use App\Http\Requests\Admin\AttendanceRecordRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\Calendar\AdminCalendarQuery;
use App\Services\Payroll\PayrollLockGuard;
use App\Support\BranchContext;
use App\Support\CalendarMonth;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The manager's attendance CRUD.
 *
 * This is the exception route, not the main way hours are recorded: staff
 * clock themselves in from {@see StaffAttendanceController}.
 * Everything written here goes through SaveManualAttendanceAction so it picks
 * up a reason, an audit entry and the closed-payroll guard.
 */
class AttendanceController extends Controller
{
    public function __construct(
        private BranchContext $branchContext,
        private SaveManualAttendanceAction $saveManual,
        private PayrollLockGuard $payrollLock,
        private AdminCalendarQuery $calendarQuery,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        $actor = $request->user();
        $monthInput = $request->string('month')->toString() ?: null;
        $calendarMonth = new CalendarMonth($monthInput);
        $month = $calendarMonth->startOfMonth();
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $branchIds = $this->branchContext->scopeIds() ?: [0];

        $records = AttendanceRecord::query()
            ->with(['employee', 'branch'])
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('work_date', [$from, $to])
            ->when($request->filled('employee_id'), fn ($query) => $query->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->string('source')->toString()))
            ->orderByDesc('work_date')
            ->orderBy('shift_name')
            ->paginate(30)
            ->withQueryString();

        $employeeId = $request->filled('employee_id') ? $request->integer('employee_id') : null;
        $baseUrl = route('admin.attendance.index');
        $filters = $request->only(['status', 'source']);

        if ($employeeId !== null) {
            $employee = User::query()
                ->active()
                ->postedTo($branchIds)
                ->find($employeeId);

            $calendar = $employee !== null
                ? $this->calendarQuery->forEmployee($actor, $employee, $monthInput, $baseUrl, $filters)
                : $this->calendarQuery->overview($actor, $monthInput, [], $baseUrl, $filters);

            // Reject out-of-scope employee: don't preserve invalid employee_id
            if ($employee === null) {
                $employeeId = null;
            }
        } else {
            $calendar = $this->calendarQuery->overview($actor, $monthInput, [], $baseUrl, $filters);
        }

        return view('admin.attendance.index', [
            'records' => $records,
            'month' => Carbon::parse($month->toDateString()),
            'summary' => $this->summary(Carbon::parse($from->toDateString()), Carbon::parse($to->toDateString()), $branchIds),
            'employees' => $this->employees(Carbon::parse($from->toDateString())),
            'statuses' => AttendanceStatus::options(),
            'sources' => AttendanceSource::options(),
            'pendingOvertimeCount' => AttendanceRecord::query()
                ->whereIn('branch_id', $branchIds)
                ->pendingOvertime()
                ->count(),
            'record' => new AttendanceRecord([
                'work_date' => now()->toDateString(),
                'shift_value' => 1,
                'status' => AttendanceStatus::Present,
            ]),
            'calendar' => $calendar,
            'calendarEmployeeId' => $employeeId,
        ]);
    }

    public function store(AttendanceRecordRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $reason = (string) $data['reason'];
        unset($data['reason']);

        $this->saveManual->create($data, $request->user(), $reason);

        return back()->with('success', 'Đã ghi nhận chấm công.');
    }

    public function edit(AttendanceRecord $attendance): View
    {
        $this->authorize('update', $attendance);

        return view('admin.attendance.form', [
            'record' => $attendance->load(['auditLogs.actor', 'shiftAssignment', 'overtimeApprover']),
            'employees' => $this->employees(),
            'statuses' => AttendanceStatus::options(),
            'isLocked' => $this->payrollLock->isLocked((int) $attendance->employee_id, $attendance->work_date),
        ]);
    }

    public function update(AttendanceRecordRequest $request, AttendanceRecord $attendance): RedirectResponse
    {
        $data = $request->validated();
        $reason = (string) $data['reason'];
        unset($data['reason']);

        $this->saveManual->update($attendance, $data, $request->user(), $reason);

        return redirect()
            ->route('admin.attendance.index', ['month' => $attendance->work_date->format('Y-m')])
            ->with('success', 'Đã cập nhật chấm công.');
    }

    public function destroy(Request $request, AttendanceRecord $attendance): RedirectResponse
    {
        $this->authorize('delete', $attendance);

        $this->saveManual->delete(
            $attendance,
            $request->user(),
            $request->string('reason')->toString() ?: null,
        );

        return back()->with('success', 'Đã xóa ca chấm công.');
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
            ->selectRaw('COALESCE(SUM(attendance_records.late_minutes), 0) as late_total')
            ->groupBy('employee_name')
            ->orderBy('employee_name')
            ->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, User> */
    private function employees(?Carbon $onDate = null)
    {
        // Only staff actually posted to the branch on that date may be clocked in.
        return User::query()
            ->active()
            ->postedTo($this->branchContext->scopeIds(), $onDate?->toDateString())
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
