<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeAssignmentRequest;
use App\Models\EmployeeBranchAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Posting staff to branches.
 *
 * Assignments are history, so an ended posting is closed with a date rather
 * than deleted; only a posting that never took effect can be removed.
 */
class EmployeeAssignmentController extends Controller
{
    public function store(EmployeeAssignmentRequest $request, User $employee): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $employee, $request): void {
            // One primary posting at a time: close the previous one the day
            // before the new one starts.
            if ($data['is_primary'] ?? false) {
                EmployeeBranchAssignment::query()
                    ->where('user_id', $employee->getKey())
                    ->where('is_primary', true)
                    ->whereNull('ends_on')
                    ->update(['ends_on' => now()->parse($data['starts_on'])->subDay()->toDateString()]);
            }

            EmployeeBranchAssignment::query()->create([
                'branch_id' => $data['branch_id'],
                'user_id' => $employee->getKey(),
                'is_primary' => (bool) ($data['is_primary'] ?? false),
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
                'created_by' => $request->user()->getKey(),
            ]);
        });

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
