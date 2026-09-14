<?php

namespace App\Policies;

use App\Models\MonthlyPaidLeaveDay;
use App\Models\User;

class MonthlyPaidLeaveDayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLeadership() || $user->isEmployee();
    }

    public function view(User $user, MonthlyPaidLeaveDay $paidLeaveDay): bool
    {
        return $paidLeaveDay->employee_id === $user->getKey()
            || ($this->manages($user) && $user->canAccessBranch($paidLeaveDay->branch_id, $paidLeaveDay->leave_date));
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, MonthlyPaidLeaveDay $paidLeaveDay): bool
    {
        return $this->manages($user)
            && $user->canAccessBranch($paidLeaveDay->branch_id, $paidLeaveDay->leave_date);
    }

    public function delete(User $user, MonthlyPaidLeaveDay $paidLeaveDay): bool
    {
        return $this->update($user, $paidLeaveDay);
    }

    private function manages(User $user): bool
    {
        return $user->isOwner() || $user->isManager();
    }
}
