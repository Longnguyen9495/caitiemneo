<?php

namespace App\Http\Requests\Admin;

use App\Enums\ServiceCategory;
use App\Enums\ServiceUnit;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service
            ? $this->user()->can('update', $service)
            : $this->user()->can('create', Service::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::enum(ServiceCategory::class)],
            'unit' => ['required', Rule::enum(ServiceUnit::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            // Khoảng giá là tùy chọn, nhưng đã khai thì phải khai cả hai đầu,
            // nếu không thì "từ 20k" mà không có trần là vô nghĩa.
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:99999999999', 'required_with:price_max'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'max:99999999999', 'required_with:price_min', 'gte:price_min'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'price_min' => $this->filled('price_min') ? $this->input('price_min') : null,
            'price_max' => $this->filled('price_max') ? $this->input('price_max') : null,
            'display_order' => $this->input('display_order', 0),
        ]);
    }

    /**
     * Giá mặc định phải nằm trong khoảng đã khai.
     *
     * Nếu không thì ô điền sẵn trên hóa đơn sẽ luôn bật cảnh báo ngay khi
     * thợ chọn dịch vụ, và cảnh báo bị bỏ qua là cảnh báo vô dụng.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('price_min') || ! $this->filled('price_max')) {
                return;
            }

            $price = (float) $this->input('price');

            if ($price < (float) $this->input('price_min') || $price > (float) $this->input('price_max')) {
                $validator->errors()->add('price', 'Giá mặc định phải nằm trong khoảng giá sàn và giá trần.');
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'tên dịch vụ',
            'description' => 'mô tả',
            'category' => 'nhóm dịch vụ',
            'unit' => 'đơn vị tính',
            'price' => 'giá niêm yết',
            'price_min' => 'giá sàn',
            'price_max' => 'giá trần',
        ];
    }
}
