<?php

namespace App\Rules;

use App\Models\EmployeeBranchAssignment;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The submitted account must be an active member of staff whose posting covers
 * the business date of the record being written.
 *
 * A plain `exists` check is not enough here: commission lines, attendance and
 * rosters all attribute money or hours to a person, so an id from another shop,
 * from a deactivated account or from a posting that has already ended is a
 * cross-branch fraud waiting to happen rather than a typo.
 *
 * The owner is deliberately accepted at every branch: they are not tied to a
 * posting, which mirrors {@see User::accessibleBranchIds()}.
 */
class AssignedToBranch implements ValidationRule
{
    public function __construct(
        private ?int $branchId,
        private mixed $onDate = null,
        private string $message = 'Nhân viên này không thuộc chi nhánh đang thao tác vào ngày đã chọn.',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $employee = User::query()->active()->staff()->find($value);

        if ($employee === null) {
            $fail($this->message);

            return;
        }

        if ($employee->isOwner()) {
            return;
        }

        if ($this->branchId === null) {
            $fail($this->message);

            return;
        }

        $covers = EmployeeBranchAssignment::query()
            ->where('user_id', $employee->getKey())
            ->where('branch_id', $this->branchId)
            ->when($this->onDate !== null, fn ($query) => $query->covering($this->onDate))
            ->exists();

        if (! $covers) {
            $fail($this->message);
        }
    }
}
