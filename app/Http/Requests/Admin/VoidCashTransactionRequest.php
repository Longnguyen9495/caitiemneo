<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VoidCashTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('void', $this->route('cash_transaction'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            // A minimum length, because a one-word reason defeats the point of
            // asking: the entry has to say enough for a reviewer to judge it.
            'void_reason' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['void_reason' => 'lý do hủy'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'void_reason.required' => 'Hãy ghi rõ lý do hủy giao dịch này.',
            'void_reason.min' => 'Lý do hủy cần mô tả cụ thể, ít nhất 10 ký tự.',
        ];
    }
}
