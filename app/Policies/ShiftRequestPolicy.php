<?php

namespace App\Policies;

use App\Enums\ShiftRequestStatus;
use App\Models\ShiftRequest;
use App\Models\User;

class ShiftRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, ShiftRequest $request): bool
    {
        return $request->requester_id === $user->getKey()
            || $request->recipient_id === $user->getKey()
            || ($this->manages($user) && $user->canAccessBranch($request->branch_id, $request->work_date));
    }

    public function create(User $user): bool
    {
        return $user->isEmployee() && $user->is_active;
    }

    public function cancel(User $user, ShiftRequest $request): bool
    {
        return $request->requester_id === $user->getKey()
            && $request->status->canBeCancelledByRequester();
    }

    public function respond(User $user, ShiftRequest $request): bool
    {
        return $request->recipient_id === $user->getKey()
            && $request->status === ShiftRequestStatus::PendingRecipient;
    }

    public function decide(User $user, ShiftRequest $request): bool
    {
        return $this->manages($user)
            && $user->canAccessBranch($request->branch_id, $request->work_date)
            && in_array($request->status, [
                ShiftRequestStatus::PendingApproval,
                ShiftRequestStatus::RecipientConfirmed,
            ], true);
    }

    public function assignReplacement(User $user, ShiftRequest $request): bool
    {
        return $this->decide($user, $request)
            || ($this->manages($user)
                && $request->status === ShiftRequestStatus::Approved
                && $user->canAccessBranch($request->branch_id, $request->work_date));
    }

    private function manages(User $user): bool
    {
        return ($user->isOwner() || $user->isManager()) && $user->is_active;
    }
}
