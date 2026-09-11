<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isManager();
    }

    /** A user may look at a branch only if they are posted to it. */
    public function view(User $user, Branch $branch): bool
    {
        return $user->canAccessBranch($branch);
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->isOwner();
    }

    /** Configuring prices, durations and stock levels of one branch. */
    public function configure(User $user, Branch $branch): bool
    {
        return ($user->isOwner() || $user->isManager()) && $user->canAccessBranch($branch);
    }
}
