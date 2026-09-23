<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /** The staff directory is visible to the leadership only. */
    public function viewAny(User $user): bool
    {
        return $user->isLeadership();
    }

    /**
     * A manager sees the people posted to the branches they run; everyone can
     * see their own record.
     */
    public function view(User $user, User $employee): bool
    {
        if ($user->is($employee) || $user->isOwner()) {
            return true;
        }

        return $user->isManager() && $this->sharesBranch($user, $employee);
    }

    /** Creating and editing accounts, including salary settings, is owner only. */
    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, User $employee): bool
    {
        return $user->isOwner();
    }

    /** Posting staff to a branch is an owner decision. */
    public function assignBranch(User $user, User $employee): bool
    {
        return $user->isOwner();
    }

    /** The shop must always keep at least one active owner. */
    public function deactivate(User $user, User $employee): bool
    {
        if (! $user->isOwner() || ! $employee->is_active) {
            return false;
        }

        if (! $employee->isOwner()) {
            return true;
        }

        return User::query()
            ->where('role', UserRole::Owner->value)
            ->where('is_active', true)
            ->whereKeyNot($employee->getKey())
            ->exists();
    }

    private function sharesBranch(User $user, User $employee): bool
    {
        return array_intersect($user->accessibleBranchIds(), $employee->accessibleBranchIds()) !== [];
    }
}
