<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Employees\AssignEmployeeToBranchAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeAssignmentRequest;
use App\Models\EmployeeBranchAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Posting staff to branches.
 *
 * Assignments are history, so an ended posting is closed with a date rather
 * than deleted; only a posting that never took effect can be removed.
 */
class EmployeeAssignmentController extends Controller
{
    public function __construct(private AssignEmployeeToBranchAction $assignToBranch) {}

    public function store(EmployeeAssignmentRequest $request, User $employee): RedirectResponse
    {
        $data = $request->validated();

        $this->assignToBranch->handle(
            employee: $employee,
            branchId: (int) $data['branch_id'],
            startsOn: $data['starts_on'],
            endsOn: $data['ends_on'] ?? null,
            isPrimary: (bool) ($data['is_primary'] ?? false),
            actor: $request->user(),
        );

        return redirect()
            ->route('admin.employees.edit', $employee)
            ->with('success', 'Đã phân công chi nhánh cho nhân viên.');
    }

    /** End a posting from a given date, keeping it in the history. */
    public function update(Request $request, User $employee, EmployeeBranchAssignment $assignment): RedirectResponse
    {
        $this->authorize('assignBranch', $employee);

        abort_unless($assignment->user_id === $employee->getKey(), 404);

        $validated = $request->validate([
            'ends_on' => ['required', 'date', 'after_or_equal:'.$assignment->starts_on->toDateString()],
        ], [
            'ends_on.after_or_equal' => 'Ngày kết thúc không được trước ngày bắt đầu.',
        ], ['ends_on' => 'ngày kết thúc']);

        $assignment->update(['ends_on' => $validated['ends_on']]);

        return redirect()
            ->route('admin.employees.edit', $employee)
            ->with('success', 'Đã kết thúc phân công.');
    }
}
