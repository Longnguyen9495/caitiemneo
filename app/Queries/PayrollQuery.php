<?php

namespace App\Queries;

use App\Models\Payroll;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PayrollQuery
{
    public function __construct(private BranchContext $branchContext) {}

    /**
     * A payroll spans branches through its allocations, so the branch filter is
     * an existence check on those rather than a column comparison.
     *
     * @return Builder<Payroll>
     */
    public function build(Request $request, User $viewer): Builder
    {
        $branchIds = $this->branchContext->scopeIds();

        return Payroll::query()
            ->unless($viewer->can('create', Payroll::class), fn (Builder $query) => $query->where('employee_id', $viewer->getKey()))
            // A payroll belongs to a branch through its allocations. One that has
            // not been calculated yet falls back to the branch that will pay it,
            // and failing that to where the employee is posted, so a payroll can
            // never become invisible to everyone.
            ->when($branchIds !== [], fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereHas('allocations', fn (Builder $allocation) => $allocation->whereIn('branch_id', $branchIds))
                ->orWhere(fn (Builder $fallback) => $fallback
                    ->doesntHave('allocations')
                    ->where(fn (Builder $unplaced) => $unplaced
                        ->whereIn('paying_branch_id', $branchIds)
                        ->orWhere(fn (Builder $viaEmployee) => $viaEmployee
                            ->whereNull('paying_branch_id')
                            ->whereHas('employee.branchAssignments', fn (Builder $assignment) => $assignment
                                ->whereIn('branch_id', $branchIds)))))))
            ->when($request->filled('employee_id'), fn (Builder $query) => $query->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('period_end', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('period_start', '<=', $request->date('to')));
    }
}
