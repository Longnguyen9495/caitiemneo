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
        return $this->view($user, $record) && ! $this->isSelfDealing($user, (int) $record->employee_id);
    }

    public function delete(User $user, AttendanceRecord $record): bool
    {
        return $this->update($user, $record);
    }

    /** Writing a hand-made shift for a named employee. */
    public function createFor(User $user, int $employeeId): bool
    {
        return $this->create($user) && ! $this->isSelfDealing($user, $employeeId);
    }

    /**
     * Whether this account would be writing its own hours.
     *
     * Attendance drives pay, so a manager amending their own shift is writing
     * their own payslip — the same reasoning that already blocks approving your
     * own overtime, applied to the record itself so the approval gate cannot be
     * walked around by editing the row instead.
     *
     * The owner is excepted because a one-owner shop would otherwise be locked
     * out of its own records entirely; their entries are flagged
     * (`is_self_recorded`) and surfaced in the review queue instead.
     */
    private function isSelfDealing(User $user, int $employeeId): bool
    {
        return $employeeId === (int) $user->getKey() && ! $user->isOwner();
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
