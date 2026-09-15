<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pay', $this->route('invoice'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::in(array_keys(PaymentMethod::invoiceOptions()))],
            'payment_proof_image' => ['required', 'image', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'payment_method' => 'phương thức thanh toán',
            'payment_proof_image' => 'ảnh chứng từ thanh toán',
        ];
    }
}
