<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkShift;

/**
 * Who may curate the shift catalogue.
 *
 * A shared template (no branch) affects every shop, so only the owner may
 * create or change one; a manager stays inside the branches they run.
 */
class WorkShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLeadership();
    }

    public function view(User $user, WorkShift $shift): bool
    {
        return $this->viewAny($user)
            && ($shift->branch_id === null || $user->canAccessBranch($shift->branch_id));
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, WorkShift $shift): bool
    {
        if ($shift->branch_id === null) {
            return $user->isOwner();
        }

        return $this->viewAny($user) && $user->canAccessBranch($shift->branch_id);
    }

    /** Templates are deactivated, never deleted, so history keeps its meaning. */
    public function delete(User $user, WorkShift $shift): bool
    {
        return false;
    }
}
