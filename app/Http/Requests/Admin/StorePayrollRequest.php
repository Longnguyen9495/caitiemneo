<?php

namespace App\Http\Requests\Admin;

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Payroll::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', Rule::exists(User::class, 'id')],
            'paying_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'adjustment' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'deduction' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'employee_id' => 'nhân viên',
            'paying_branch_id' => 'chi nhánh trả lương',
            'period_start' => 'từ ngày',
            'period_end' => 'đến ngày',
            'adjustment' => 'phụ cấp',
            'deduction' => 'khấu trừ',
            'note' => 'ghi chú',
        ];
    }
}
