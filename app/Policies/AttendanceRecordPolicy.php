<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isManager();
    }

    public function view(User $user, AttendanceRecord $record): bool
    {
        return $this->viewAny($user) && $user->canAccessBranch($record->branch_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AttendanceRecord $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, AttendanceRecord $record): bool
    {
        return $this->view($user, $record);
    }

    /**
     * Deciding on somebody's overtime.
     *
     * Explicitly not the employee themselves, even for their own row: the
     * whole point of the pending state is that a second person signs it off.
     */
    public function reviewOvertime(User $user, AttendanceRecord $record): bool
    {
        if ((int) $record->employee_id === (int) $user->getKey() && ! $user->isOwner()) {
            return false;
        }

        return $this->view($user, $record);
    }

    /** An employee may always read back their own attendance history. */
    public function viewOwn(User $user, AttendanceRecord $record): bool
    {
        return (int) $record->employee_id === (int) $user->getKey() || $this->view($user, $record);
    }
}
