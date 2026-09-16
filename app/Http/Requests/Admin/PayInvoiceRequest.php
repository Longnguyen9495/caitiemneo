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
            'payment_proof_image' => ['bail', 'required', 'mimes:jpg,jpeg,png,webp', 'image', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'payment_proof_image.mimes' => 'Ảnh chứng từ thanh toán phải là tệp JPG, PNG hoặc WebP.',
            'payment_proof_image.max' => 'Ảnh chứng từ thanh toán không được vượt quá 5 MB.',
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
