<?php

namespace App\Rules;

use App\Models\EmployeeBranchAssignment;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The submitted employee must be somebody the signed-in actor supervises.
 *
 * Payroll spans a period rather than a single branch-day, so membership is
 * asked of the actor's whole scope: a manager may build a salary only for staff
 * posted to a branch they run, which stops a tampered id from pulling another
 * shop's employee — and their commission history — into a payroll the manager
 * controls.
 */
class EmployeeWithinActorScope implements ValidationRule
{
    public function __construct(
        private ?User $actor,
        private string $message = 'Nhân viên này không thuộc phạm vi chi nhánh bạn quản lý.',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->actor === null) {
            $fail($this->message);

            return;
        }

        $employee = User::query()->active()->staff()->find($value);

        if ($employee === null) {
            $fail($this->message);

            return;
        }

        // The owner runs the company, so every account is in scope for them.
        if ($this->actor->isOwner()) {
            return;
        }

        $accessible = $this->actor->accessibleBranchIds();

        if ($accessible === []) {
            $fail($this->message);

            return;
        }

        $shared = EmployeeBranchAssignment::query()
            ->where('user_id', $employee->getKey())
            ->whereIn('branch_id', $accessible)
            ->exists();

        if (! $shared) {
            $fail($this->message);
        }
    }
}
