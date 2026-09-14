<?php

namespace App\Policies;

use App\Models\EmployeeFixedShift;
use App\Models\User;

class EmployeeFixedShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLeadership() || $user->isEmployee();
    }

    public function view(User $user, EmployeeFixedShift $fixedShift): bool
    {
        return $fixedShift->employee_id === $user->getKey()
            || ($this->manages($user) && $user->canAccessBranch($fixedShift->branch_id, $fixedShift->effective_from));
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, EmployeeFixedShift $fixedShift): bool
    {
        return $this->manages($user)
            && $user->canAccessBranch($fixedShift->branch_id, $fixedShift->effective_from);
    }

    public function delete(User $user, EmployeeFixedShift $fixedShift): bool
    {
        return $this->update($user, $fixedShift);
    }

    private function manages(User $user): bool
    {
        return $user->isOwner() || $user->isManager();
    }
}
