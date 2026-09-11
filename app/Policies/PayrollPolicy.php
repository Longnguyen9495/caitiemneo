<?php

namespace App\Policies;

use App\Models\Payroll;
use App\Models\User;

class PayrollPolicy
{
    /** Everyone can open the payroll screen, but employees only ever see their own rows. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * A payroll is visible to its owner, and to a manager who runs at least one
     * of the branches the payroll is allocated to.
     */
    public function view(User $user, Payroll $payroll): bool
    {
        if ($payroll->employee_id === $user->getKey()) {
            return true;
        }

        return $this->manage($user) && $this->touchesUserBranch($user, $payroll);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Payroll $payroll): bool
    {
        return $this->manage($user)
            && $this->touchesUserBranch($user, $payroll)
            && $payroll->isEditable();
    }

    /** Closing a period and paying it out is reserved for the owner. */
    public function finalize(User $user, Payroll $payroll): bool
    {
        return $user->isOwner();
    }

    public function pay(User $user, Payroll $payroll): bool
    {
        return $user->isOwner();
    }

    public function cancel(User $user, Payroll $payroll): bool
    {
        return $user->isOwner();
    }

    /** Overriding the calculated total always needs the owner's signature. */
    public function approveVariance(User $user, Payroll $payroll): bool
    {
        return $user->isOwner();
    }

    /**
     * Managers need an explicit grant before they may touch salaries; the flag is
     * set per account by the owner.
     */
    private function manage(User $user): bool
    {
        return $user->isOwner() || ($user->isManager() && $user->can_manage_payroll);
    }

    /**
     * A payroll with no allocations yet (a draft just created) falls back to the
     * employee's own posting so the creator does not lock themselves out.
     */
    private function touchesUserBranch(User $user, Payroll $payroll): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        $accessible = $user->accessibleBranchIds();
        $payrollBranchIds = $payroll->allocations()->pluck('branch_id')->all();

        if ($payrollBranchIds === []) {
            $payrollBranchIds = array_filter([$payroll->paying_branch_id]);
        }

        return $payrollBranchIds === []
            || array_intersect($payrollBranchIds, $accessible) !== [];
    }
}
