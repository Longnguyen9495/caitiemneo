<?php

namespace App\Http\Requests\Admin;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch
            ? $this->user()->can('update', $branch)
            : $this->user()->can('create', Branch::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $branch = $this->route('branch');

        return [
            'code' => [
                'required', 'string', 'max:30', 'alpha_dash',
                Rule::unique(Branch::class, 'code')->ignore($branch instanceof Branch ? $branch->getKey() : null),
            ],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => 'mã chi nhánh',
            'name' => 'tên chi nhánh',
            'address' => 'địa chỉ',
            'phone' => 'số điện thoại',
        ];
    }
}
