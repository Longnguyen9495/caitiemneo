<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BranchCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('configure', $this->route('branch'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'services' => ['nullable', 'array'],
            'services.*.enabled' => ['nullable', 'boolean'],
            'services.*.is_active' => ['nullable', 'boolean'],
            'services.*.price' => ['required_with:services.*.enabled', 'numeric', 'min:0', 'max:99999999999'],
            'services.*.duration_minutes' => ['required_with:services.*.enabled', 'integer', 'min:5', 'max:480'],
            'services.*.commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'services.*.overtime_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'products' => ['nullable', 'array'],
            'products.*.enabled' => ['nullable', 'boolean'],
            'products.*.is_active' => ['nullable', 'boolean'],
            'products.*.minimum_stock' => ['required_with:products.*.enabled', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'services.*.price' => 'giá dịch vụ',
            'services.*.duration_minutes' => 'thời lượng',
            'products.*.minimum_stock' => 'định mức tồn',
        ];
    }
}
