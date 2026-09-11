<?php

namespace App\Rules;

use App\Models\Branch;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The submitted branch must be one the signed-in actor is posted to.
 *
 * Used where a form offers a branch picker rather than taking the branch from
 * the request context: the picker is filtered in the view, and this rule is
 * what actually holds when the payload is edited by hand.
 */
class WithinActorBranchScope implements ValidationRule
{
    public function __construct(
        private ?User $actor,
        private string $message = 'Bạn không có quyền thao tác trên chi nhánh này.',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->actor === null || ! $this->actor->canAccessBranch((int) $value)) {
            $fail($this->message);

            return;
        }

        $isActive = Branch::query()->whereKey($value)->where('is_active', true)->exists();

        if (! $isActive) {
            $fail($this->message);
        }
    }
}
