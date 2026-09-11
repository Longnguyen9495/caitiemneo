<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Attendance\ScheduleShiftAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShiftAssignmentRequest;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The weekly roster.
 *
 * An employee may open this page but only ever sees their own row; the
 * narrowing happens in the query rather than in the view, so a hand-typed
 * query string cannot widen it.
 */
class ShiftScheduleController extends Controller
{
    public function __construct(private BranchContext $branchContext) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ShiftAssignment::class);

        $user = $request->user();
        $weekStart = $this->weekStart($request);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $branchIds = $this->branchContext->scopeIds() ?: [0];
        $managesRoster = $user->can('create', ShiftAssignment::class);

        $assignments = ShiftAssignment::query()
            ->with(['employee:id,name', 'branch:id,code,name', 'workShift:id,name', 'attendanceRecord:id,shift_assignment_id,status'])
            ->whereIn('branch_id', $branchIds)
            ->betweenDates($weekStart->toDateString(), $weekEnd->toDateString())
            ->unless($managesRoster, fn ($query) => $query->where('employee_id', $user->getKey()))
            ->orderBy('planned_start_at')
            ->get();

        return view('admin.shift-schedule.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => $this->daysOfWeek($weekStart),
            'rows' => $this->groupByEmployee($assignments),
            'assignments' => $assignments,
            'managesRoster' => $managesRoster,
            'employees' => $managesRoster ? $this->employees($weekStart) : new \Illuminate\Database\Eloquent\Collection,
            'shifts' => $managesRoster ? $this->usableShifts() : new \Illuminate\Database\Eloquent\Collection,
            'defaultDate' => $this->defaultDate($weekStart, $weekEnd)->toDateString(),
        ]);
    }

    public function store(ShiftAssignmentRequest $request, ScheduleShiftAction $schedule): RedirectResponse
    {
        $data = $request->validated();

        $schedule->handle(
            Branch::query()->findOrFail($data['branch_id']),
            WorkShift::query()->findOrFail($data['work_shift_id']),
            User::query()->findOrFail($data['employee_id']),
            Carbon::parse($data['work_date'])->toDateString(),
            $request->user(),
            $data['note'] ?? null,
        );

        return back()->with('success', 'Đã phân ca.');
    }

    public function destroy(ShiftAssignment $shiftAssignment): RedirectResponse
    {
        $this->authorize('delete', $shiftAssignment);

        // A worked shift is evidence; unrostering it would orphan the
        // attendance row it produced.
        if ($shiftAssignment->attendanceRecord()->exists()) {
            return back()->withErrors([
                'work_shift_id' => 'Ca này đã có dữ liệu chấm công nên không thể bỏ phân ca. Hãy sửa bản ghi chấm công thay vì xóa lịch.',
            ]);
        }

        $shiftAssignment->delete();

        return back()->with('success', 'Đã bỏ phân ca.');
    }

    /** Weeks run Monday to Sunday, which is how the shop talks about them. */
    private function weekStart(Request $request): Carbon
    {
        $raw = $request->string('week')->toString();

        return ($raw !== '' ? Carbon::parse($raw) : now())->startOfWeek(Carbon::MONDAY);
    }

    /** @return array<int, Carbon> */
    private function daysOfWeek(Carbon $weekStart): array
    {
        return array_map(fn (int $offset): Carbon => $weekStart->copy()->addDays($offset), range(0, 6));
    }

    /**
     * Roster rows keyed by employee, then by date, for the weekly grid.
     *
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @return Collection<int, array{employee: User|null, days: array<string, Collection<int, ShiftAssignment>>}>
     */
    private function groupByEmployee(Collection $assignments): Collection
    {
        return $assignments
            ->groupBy('employee_id')
            ->map(fn (Collection $rows): array => [
                'employee' => $rows->first()->employee,
                'days' => $rows->groupBy(fn (ShiftAssignment $row): string => $row->work_date->toDateString())->all(),
            ])
            ->sortBy(fn (array $row): string => $row['employee']?->name ?? '')
            ->values();
    }

    /** Staff posted to the active branch at some point during the week. */
    private function employees(Carbon $weekStart)
    {
        $branchIds = $this->branchContext->scopeIds();
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        return User::query()
            ->active()
            ->staff()
            ->when($branchIds !== [], fn ($query) => $query->whereHas('branchAssignments', fn ($assignment) => $assignment
                ->whereIn('branch_id', $branchIds)
                ->overlappingWindow($weekStart->toDateString(), $weekEnd->toDateString())))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function usableShifts()
    {
        return WorkShift::query()
            ->active()
            ->usableAt($this->branchContext->scopeIds())
            ->orderBy('starts_at')
            ->get();
    }

    /** Default the quick-add form to today when today is in view. */
    private function defaultDate(Carbon $weekStart, Carbon $weekEnd): Carbon
    {
        return now()->between($weekStart, $weekEnd) ? now() : $weekStart;
    }
}
