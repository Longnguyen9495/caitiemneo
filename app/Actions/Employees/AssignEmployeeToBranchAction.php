<?php

namespace App\Actions\Employees;

use App\Models\EmployeeBranchAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Post one employee to one branch from a given date.
 *
 * Shared by the create-account screen and the assignment panel so that both
 * doors into the posting history close the previous primary the same way.
 */
class AssignEmployeeToBranchAction
{
    public function handle(
        User $employee,
        int $branchId,
        string $startsOn,
        ?string $endsOn = null,
        bool $isPrimary = true,
        ?User $actor = null,
    ): EmployeeBranchAssignment {
        return DB::transaction(function () use ($employee, $branchId, $startsOn, $endsOn, $isPrimary, $actor): EmployeeBranchAssignment {
            // One primary posting at a time: close the previous one the day
            // before the new one starts.
            if ($isPrimary) {
                EmployeeBranchAssignment::query()
                    ->where('user_id', $employee->getKey())
                    ->where('is_primary', true)
                    ->whereNull('ends_on')
                    ->update(['ends_on' => Carbon::parse($startsOn)->subDay()->toDateString()]);
            }

            return EmployeeBranchAssignment::query()->create([
                'branch_id' => $branchId,
                'user_id' => $employee->getKey(),
                'is_primary' => $isPrimary,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'created_by' => $actor?->getKey(),
            ]);
        });
    }
}
