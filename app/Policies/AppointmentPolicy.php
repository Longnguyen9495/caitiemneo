<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isManager() || $user->can_manage_appointments || $user->isEmployee();
    }

    /**
     * Branch isolation is enforced here rather than only in the list query, so
     * typing another branch's id into the URL returns 403 instead of data.
     */
    public function view(User $user, Appointment $appointment): bool
    {
        if (! $this->viewAny($user) || ! $user->canAccessBranch($appointment->branch_id)) {
            return false;
        }

        return ! $user->isEmployee()
            || $user->can_manage_appointments
            || (int) $appointment->employee_id === (int) $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->isOwner() || $user->isManager() || $user->can_manage_appointments;
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->create($user) && $this->view($user, $appointment);
    }

    /** An assigned employee may only move their own appointment through its workflow. */
    public function updateStatus(User $user, Appointment $appointment): bool
    {
        return $this->view($user, $appointment);
    }

    /** Turning an appointment into an invoice belongs to the invoicing permission. */
    public function convertToInvoice(User $user, Appointment $appointment): bool
    {
        return ($user->isOwner() || $user->isManager() || $user->can_create_invoices)
            && $user->canAccessBranch($appointment->branch_id);
    }
}
