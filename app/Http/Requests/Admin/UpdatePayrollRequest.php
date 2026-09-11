<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('payroll'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'adjustment' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'deduction' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'adjustment' => 'phụ cấp',
            'deduction' => 'khấu trừ',
            'note' => 'ghi chú',
        ];
    }
}
