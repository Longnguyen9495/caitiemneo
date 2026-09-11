<?php

namespace App\Policies;

use App\Models\CashTransaction;
use App\Models\User;

class CashTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isManager();
    }

    public function view(User $user, CashTransaction $transaction): bool
    {
        return $this->viewAny($user) && $user->canAccessBranch($transaction->branch_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /** Only hand-made, still active entries of an accessible branch may be edited. */
    public function update(User $user, CashTransaction $transaction): bool
    {
        return $this->view($user, $transaction)
            && ! $transaction->isSystemGenerated()
            && ! $transaction->isVoided();
    }

    public function void(User $user, CashTransaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }
}
