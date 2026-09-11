<?php

namespace App\Policies;

use App\Models\InventoryMovement;
use App\Models\User;

class InventoryMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InventoryMovement $movement): bool
    {
        return $user->canAccessBranch($movement->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->isOwner() || $user->isManager();
    }
}
