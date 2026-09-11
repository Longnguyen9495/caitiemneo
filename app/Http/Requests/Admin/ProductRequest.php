<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', Product::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'nullable', 'string', 'max:100',
                Rule::unique(Product::class, 'sku')->ignore($product instanceof Product ? $product->getKey() : null),
            ],
            'unit' => ['required', 'string', 'max:50'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:9999999999'],
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
            'name' => 'tên vật tư',
            'sku' => 'mã SKU',
            'unit' => 'đơn vị tính',
            'cost_price' => 'giá vốn',
            'minimum_stock' => 'định mức tồn tối thiểu',
        ];
    }
}
