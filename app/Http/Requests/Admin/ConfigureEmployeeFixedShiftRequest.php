<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ResolvesBranch;
use App\Models\EmployeeFixedShift;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigureEmployeeFixedShiftRequest extends FormRequest
{
    use ResolvesBranch;

    public function authorize(): bool
    {
        return $this->user()->can('create', EmployeeFixedShift::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => $this->branchRules(),
            'employee_id' => ['required', 'integer', Rule::exists(User::class, 'id')],
            'work_shift_id' => ['required', 'integer', Rule::exists(WorkShift::class, 'id')],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

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
            'work_shift_id' => 'ca cố định',
            'effective_from' => 'ngày hiệu lực',
            'effective_to' => 'ngày kết thúc',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->branchMessages();
    }
}
