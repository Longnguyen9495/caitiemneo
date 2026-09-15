<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\BankDirectory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            // Chỉ nhận ngân hàng có trong danh mục. Tên gõ tay sai một chữ là
            // người trả lương phải dò lại, nên thà chặn ngay ở đây.
            'bank_name' => ['nullable', 'string', Rule::in(BankDirectory::names())],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return [
            'phone' => 'số điện thoại',
            'bank_name' => 'ngân hàng',
            'bank_account_holder' => 'tên chủ tài khoản',
            'bank_account_number' => 'số tài khoản',
            'avatar' => 'ảnh đại diện',
        ];
    }
}
