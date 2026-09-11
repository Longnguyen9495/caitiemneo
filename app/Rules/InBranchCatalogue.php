<?php

namespace App\Rules;

use App\Models\BranchService;
use App\Models\Service;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The service must be active and offered by the branch that owns the record.
 *
 * Without this, a tampered `service_id` from another shop's menu reaches the
 * invoice and is silently dropped by the action, which loses the line's
 * provenance instead of telling the operator the menu item is wrong.
 */
class InBranchCatalogue implements ValidationRule
{
    public function __construct(private ?int $branchId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $message = 'Dịch vụ này không nằm trong bảng giá của chi nhánh.';

        if ($this->branchId === null) {
            $fail($message);

            return;
        }

        $offered = BranchService::query()
            ->where('branch_id', $this->branchId)
            ->where('service_id', $value)
            ->where('is_active', true)
            ->whereHas('service', fn ($query) => $query->where('is_active', true))
            ->exists();

        if (! $offered) {
            $fail($message);
        }
    }
}
