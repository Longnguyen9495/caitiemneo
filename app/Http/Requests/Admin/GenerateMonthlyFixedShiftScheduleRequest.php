<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ResolvesBranch;
use App\Models\EmployeeFixedShift;
use Illuminate\Foundation\Http\FormRequest;

class GenerateMonthlyFixedShiftScheduleRequest extends FormRequest
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
            'month' => ['required', 'date_format:Y-m'],
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
            'month' => 'tháng tạo lịch',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->branchMessages();
    }
}
