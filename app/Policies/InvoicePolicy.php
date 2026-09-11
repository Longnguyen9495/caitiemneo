<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isManager() || $user->can_create_invoices;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->viewAny($user) && $user->canAccessBranch($invoice->branch_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $invoice->isEditable();
    }

    public function pay(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $invoice->isEditable();
    }

    /** Reversing money that has already been collected stays with the leadership. */
    public function cancel(User $user, Invoice $invoice): bool
    {
        return ($user->isOwner() || $user->isManager()) && $user->canAccessBranch($invoice->branch_id);
    }

    /** Marking an invoice as counting towards the bill KPI. */
    public function verifyBillKpi(User $user, Invoice $invoice): bool
    {
        return $this->cancel($user, $invoice);
    }

    /** Approving an out-of-hours line, which pays a higher commission. */
    public function approveOvertime(User $user, Invoice $invoice): bool
    {
        return $this->cancel($user, $invoice);
    }
}
