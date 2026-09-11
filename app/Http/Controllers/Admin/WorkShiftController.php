<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorkShiftRequest;
use App\Models\Branch;
use App\Models\WorkShift;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WorkShiftController extends Controller
{
    public function __construct(private BranchContext $branchContext) {}

    public function index(): View
    {
        $this->authorize('viewAny', WorkShift::class);

        return view('admin.work-shifts.index', [
            'shifts' => WorkShift::query()
                ->with('branch')
                ->withCount('assignments')
                ->usableAt($this->branchContext->scopeIds())
                ->orderByDesc('is_active')
                ->orderBy('starts_at')
                ->paginate(30),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', WorkShift::class);

        return view('admin.work-shifts.form', [
            'shift' => new WorkShift([
                'branch_id' => $this->branchContext->requireWritableBranchId(),
                'starts_at' => '09:00',
                'ends_at' => '19:00',
                'shift_value' => 1,
                'grace_minutes' => 0,
                'is_active' => true,
            ]),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function store(WorkShiftRequest $request): RedirectResponse
    {
        WorkShift::query()->create($request->validated());

        return redirect()->route('admin.work-shifts.index')->with('success', 'Đã thêm ca làm.');
    }

    public function edit(WorkShift $workShift): View
    {
        $this->authorize('update', $workShift);

        return view('admin.work-shifts.form', [
            'shift' => $workShift,
            'branches' => $this->branchOptions(),
        ]);
    }

    public function update(WorkShiftRequest $request, WorkShift $workShift): RedirectResponse
    {
        $workShift->update($request->validated());

        return redirect()->route('admin.work-shifts.index')->with('success', 'Đã cập nhật ca làm.');
    }

    /**
     * Branches this account may attach a template to.
     *
     * @return Collection<int, Branch>
     */
    private function branchOptions()
    {
        return Branch::query()
            ->active()
            ->whereIn('id', request()->user()->accessibleBranchIds())
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }
}
