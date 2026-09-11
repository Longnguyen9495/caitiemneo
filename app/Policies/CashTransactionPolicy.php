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

    /**
     * Only hand-made, still active entries of an accessible branch may be
     * edited — and never by the person who wrote them.
     *
     * Booking an entry and then amending or unwinding it yourself is how a
     * shortfall gets papered over, so the maker is never the checker. This is
     * separation of duties, not a statement about the person: it applies to the
     * owner too, because "the owner can always undo their own entry" is exactly
     * the hole a stolen owner session walks through.
     */
    public function update(User $user, CashTransaction $transaction): bool
    {
        return $this->view($user, $transaction)
            && ! $transaction->isSystemGenerated()
            && ! $transaction->isVoided()
            && ! $this->isOwnEntry($user, $transaction);
    }

    public function void(User $user, CashTransaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }

    /** Whether this account is the one that booked the entry. */
    public function isOwnEntry(User $user, CashTransaction $transaction): bool
    {
        return $transaction->created_by !== null
            && (int) $transaction->created_by === (int) $user->getKey();
    }
}
