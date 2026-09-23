<?php

namespace App\Services\Shifts;

use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the two rostering screens are allowed to offer.
 *
 * The weekly roster and the monthly fixed-shift plan fill the same two
 * dropdowns from the same rules, and each used to build them for itself. That
 * is how the lists drift: one screen starts offering somebody the other has
 * already stopped offering, and only the write path notices.
 *
 * Only the columns the option lists actually render are selected — a name, and
 * whatever {@see WorkShift::label()} needs to spell out a time range.
 */
final class ShiftPlanningOptions
{
    /**
     * Staff who may be rostered at these branches during a window.
     *
     * Passing no end date asks about a single day. A closed account is left
     * out, matching the refusal the write path would give anyway.
     *
     * @param  array<int, int>  $branchIds
     * @return Collection<int, User>
     */
    public function staff(array $branchIds, string $from, ?string $to = null): Collection
    {
        return User::query()
            ->active()
            ->staff()
            ->postedTo($branchIds, $from, $to)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Shift templates these branches may roster from: their own and the shared ones.
     *
     * @param  array<int, int>  $branchIds
     * @return Collection<int, WorkShift>
     */
    public function shifts(array $branchIds): Collection
    {
        return WorkShift::query()
            ->active()
            ->usableAt($branchIds)
            ->orderBy('starts_at')
            ->get(['id', 'branch_id', 'name', 'starts_at', 'ends_at']);
    }
}
