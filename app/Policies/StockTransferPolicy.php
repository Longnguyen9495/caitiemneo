<?php

namespace App\Policies;

use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use App\Models\User;

class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isManager();
    }

    /** Either end of the transfer has to be a branch the user is posted to. */
    public function view(User $user, StockTransfer $transfer): bool
    {
        return $this->viewAny($user) && $this->touchesUserBranch($user, $transfer);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, StockTransfer $transfer): bool
    {
        return $this->view($user, $transfer) && $transfer->isEditable();
    }

    /**
     * Completing moves stock out of the source branch, so the user must be
     * allowed to act in that branch specifically, not merely see the transfer.
     */
    public function complete(User $user, StockTransfer $transfer): bool
    {
        return $this->viewAny($user)
            && $transfer->status === StockTransferStatus::Draft
            && $user->canAccessBranch($transfer->source_branch_id);
    }

    public function cancel(User $user, StockTransfer $transfer): bool
    {
        return $this->viewAny($user)
            && $transfer->status === StockTransferStatus::Draft
            && $this->touchesUserBranch($user, $transfer);
    }

    private function touchesUserBranch(User $user, StockTransfer $transfer): bool
    {
        return $user->canAccessBranch($transfer->source_branch_id)
            || $user->canAccessBranch($transfer->destination_branch_id);
    }
}
