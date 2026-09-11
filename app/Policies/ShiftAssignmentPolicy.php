<?php

namespace App\Policies;

use App\Models\ShiftAssignment;
use App\Models\User;

/**
 * Who may see and write the roster.
 *
 * Every signed-in member of staff may look at the roster page, but an employee
 * only ever sees their own rows — that filtering is done in the query, and
 * `view()` here is the per-row backstop.
 */
class ShiftAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isManager() || $user->isEmployee();
    }

    public function view(User $user, ShiftAssignment $assignment): bool
    {
        if ((int) $assignment->employee_id === (int) $user->getKey()) {
            return true;
        }

        return $this->manages($user) && $user->canAccessBranch($assignment->branch_id, $assignment->work_date);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, ShiftAssignment $assignment): bool
    {
        return $this->manages($user) && $user->canAccessBranch($assignment->branch_id, $assignment->work_date);
    }

    public function delete(User $user, ShiftAssignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }

    private function manages(User $user): bool
    {
        return $user->isOwner() || $user->isManager();
    }
}
