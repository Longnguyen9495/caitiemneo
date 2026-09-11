<?php

namespace App\Http\Requests\Admin;

use App\Models\Branch;
use App\Models\EmployeeBranchAssignment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignBranch', $this->route('employee'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', Rule::exists(Branch::class, 'id')->where('is_active', true)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_primary' => $this->boolean('is_primary')]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $employee = $this->route('employee');

            if (! $employee instanceof User || $validator->errors()->isNotEmpty()) {
                return;
            }

            // The same person cannot hold two overlapping postings at one shop.
            $overlaps = EmployeeBranchAssignment::query()
                ->where('user_id', $employee->getKey())
                ->where('branch_id', $this->input('branch_id'))
                ->overlappingWindow($this->date('starts_on')?->toDateString(), $this->date('ends_on')?->toDateString())
                ->exists();

            if ($overlaps) {
                $validator->errors()->add('starts_on', 'Nhân viên đã có phân công trùng khoảng thời gian tại chi nhánh này.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ends_on.after_or_equal' => 'Ngày kết thúc không được trước ngày bắt đầu.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'branch_id' => 'chi nhánh',
            'starts_on' => 'ngày bắt đầu',
            'ends_on' => 'ngày kết thúc',
        ];
    }
}
