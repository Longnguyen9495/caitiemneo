<?php

namespace App\Actions\Shifts\Concerns;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The one condition every write in this folder has to clear first.
 *
 * Rostering, approving a request and assigning a stand-in all ask the same
 * question — is this person leadership at that shop on that business date —
 * and each used to spell the condition out for itself. Four copies of a
 * permission check is four places to forget the date, which is the part that
 * matters: a manager who left a branch in March must not be able to approve
 * something dated April.
 *
 * Only the wording stays with each caller, because a refusal is most useful
 * when it names the thing that was refused.
 */
trait AssertsBranchLeadership
{
    /** @throws ValidationException */
    private function assertLeadsBranchOn(User $actor, ?int $branchId, mixed $onDate, string $field, string $message): void
    {
        if ($actor->isLeadership() && $actor->canAccessBranch($branchId, $onDate)) {
            return;
        }

        throw ValidationException::withMessages([$field => $message]);
    }
}
