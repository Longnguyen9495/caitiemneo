<?php

namespace App\Http\Requests\Admin;

use App\Enums\PayrollAdjustmentCategory;
use App\Enums\PayrollAdjustmentDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('payroll'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'category' => ['required', Rule::in(array_keys(PayrollAdjustmentCategory::manualOptions()))],
            'direction' => ['required', Rule::enum(PayrollAdjustmentDirection::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category.in' => 'Hạng mục này do hệ thống tự tính, không thể nhập tay.',
            'description.required' => 'Khoản điều chỉnh thủ công bắt buộc phải có diễn giải.',
            'amount.gt' => 'Số tiền phải lớn hơn 0. Chiều cộng hay trừ do trường "Loại" quyết định.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'branch_id' => 'chi nhánh',
            'category' => 'hạng mục',
            'direction' => 'loại',
            'amount' => 'số tiền',
            'description' => 'diễn giải',
        ];
    }
}
