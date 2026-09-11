<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Attendance\ReviewOvertimeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewOvertimeRequest;
use App\Models\AttendanceAuditLog;
use App\Models\AttendanceRecord;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The exceptions queue: overruns awaiting a decision, shifts left open, and
 * the audit trail of everything that has been touched by hand.
 */
class AttendanceReviewController extends Controller
{
    public function __construct(private BranchContext $branchContext) {}

    public function index(): View
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        $branchIds = $this->branchContext->scopeIds() ?: [0];

        return view('admin.attendance.review', [
            'pendingOvertime' => $this->scoped($branchIds)->pendingOvertime()->orderByDesc('work_date')->limit(50)->get(),
            'openShifts' => $this->scoped($branchIds)
                ->missingCheckOut()
                ->whereDate('work_date', '<', now()->toDateString())
                ->orderByDesc('work_date')
                ->limit(50)
                ->get(),
            'flaggedGps' => $this->scoped($branchIds)->gpsNeedsReview()->orderByDesc('work_date')->limit(50)->get(),
            'auditLogs' => AttendanceAuditLog::query()
                ->with(['actor:id,name', 'attendanceRecord:id,employee_id,work_date,shift_name', 'attendanceRecord.employee:id,name'])
                ->whereIn('branch_id', $branchIds)
                ->latest('id')
                ->limit(40)
                ->get(),
        ]);
    }

    public function update(ReviewOvertimeRequest $request, AttendanceRecord $attendance, ReviewOvertimeAction $review): RedirectResponse
    {
        $record = $review->handle(
            $attendance,
            $request->user(),
            $request->decision(),
            $request->approvedMinutes(),
            $request->string('overtime_approval_note')->toString() ?: null,
        );

        return back()->with('success', sprintf(
            'Đã ghi nhận quyết định tăng ca: %s.',
            $record->overtime_status->label(),
        ));
    }

    /** @param  array<int, int>  $branchIds */
    private function scoped(array $branchIds)
    {
        return AttendanceRecord::query()
            ->with(['employee:id,name', 'branch:id,code,name', 'shiftAssignment:id,planned_start_at,planned_end_at'])
            ->whereIn('branch_id', $branchIds);
    }
}
