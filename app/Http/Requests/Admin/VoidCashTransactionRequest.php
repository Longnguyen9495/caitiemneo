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
            'void_reason' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['void_reason' => 'lý do hủy'];
    }
}
