<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ResolvesBranch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShiftAssignmentRequest extends FormRequest
{
    use ResolvesBranch;

    public function authorize(): bool
    {
        return $this->user()->can('create', ShiftAssignment::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => $this->branchRules(),
            'employee_id' => ['required', 'integer', Rule::exists(User::class, 'id')],
            'work_shift_id' => ['required', 'integer', Rule::exists(WorkShift::class, 'id')],
            'work_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The branch always comes from the resolved context.
     *
     * Whatever the form posted is discarded, so a hidden field cannot roster
     * somebody into a shop the signed-in manager does not run.
     */
    protected function prepareForValidation(): void
    {
        $this->mergeResolvedBranch();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'branch_id' => 'chi nhánh',
            'employee_id' => 'nhân viên',
            'work_shift_id' => 'ca làm',
            'work_date' => 'ngày làm',
            'note' => 'ghi chú',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->branchMessages();
    }
}
