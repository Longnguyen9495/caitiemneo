<?php

namespace App\Http\Requests\Concerns;

use App\Support\BranchContext;

/**
 * Supplies the branch a write belongs to.
 *
 * The value is taken from the validated branch context of the request, never
 * from the submitted payload, so a tampered hidden field cannot move a record
 * into another shop.
 */
trait ResolvesBranch
{
    protected function contextBranchId(): ?int
    {
        return app(BranchContext::class)->requireWritableBranchId();
    }

    /** Overwrite whatever the client sent with the branch the server resolved. */
    protected function mergeResolvedBranch(): void
    {
        $this->merge(['branch_id' => $this->contextBranchId()]);
    }

    /** @return array<int, mixed> */
    protected function branchRules(): array
    {
        return ['required', 'integer', 'exists:branches,id'];
    }

    /** @return array<string, string> */
    protected function branchMessages(): array
    {
        return [
            'branch_id.required' => 'Hãy chọn một chi nhánh cụ thể trước khi thực hiện thao tác này.',
        ];
    }
}
