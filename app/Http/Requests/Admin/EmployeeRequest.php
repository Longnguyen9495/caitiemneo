<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof User
            ? $this->user()->can('update', $employee)
            : $this->user()->can('create', User::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $ignoreId = $employee instanceof User ? $employee->getKey() : null;

        return [
            // A brand new account is posted to a branch in the same step, because an
            // account without a posting cannot reach the admin area at all.
            'branch_id' => $ignoreId
                ? ['prohibited']
                : ['required', Rule::exists(Branch::class, 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:60', 'alpha_dash', Rule::unique(User::class, 'username')->ignore($ignoreId)],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($ignoreId)],
            'password' => [$ignoreId ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'base_salary' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'shift_rate' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'can_manage_appointments' => ['nullable', 'boolean'],
            'can_create_invoices' => ['nullable', 'boolean'],
            'can_manage_payroll' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'can_manage_appointments' => $this->boolean('can_manage_appointments'),
            'can_create_invoices' => $this->boolean('can_create_invoices'),
            'can_manage_payroll' => $this->boolean('can_manage_payroll'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'branch_id' => 'chi nhánh',
            'name' => 'họ tên',
            'username' => 'tên đăng nhập',
            'email' => 'email',
            'password' => 'mật khẩu',
            'role' => 'vai trò',
            'phone' => 'số điện thoại',
            'base_salary' => 'lương cứng',
            'shift_rate' => 'đơn giá mỗi ca',
            'commission_rate' => 'tỷ lệ hoa hồng',
        ];
    }
}
