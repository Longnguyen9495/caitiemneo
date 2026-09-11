<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Enums\WorkContext;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invoice'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'discount' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array', 'max:100'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.service_id' => ['nullable', Rule::exists(Service::class, 'id')],
            'items.*.employee_id' => ['nullable', Rule::exists(User::class, 'id')],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01', 'max:9999'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0', 'max:99999999999'],
            'items.*.commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.commission_rate_reason' => ['nullable', 'string', 'max:255'],
            'items.*.work_context' => ['nullable', Rule::enum(WorkContext::class)],
        ];
    }

    /**
     * An editor with every line removed submits no `items` key at all, because
     * browsers omit empty arrays. The form ships a marker so that case can be
     * told apart from a request that never touches the lines.
     */
    protected function prepareForValidation(): void
    {
        if ($this->boolean('items_submitted') && ! $this->has('items')) {
            $this->merge(['items' => []]);
        }
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_name' => 'tên khách hàng',
            'customer_phone' => 'số điện thoại',
            'discount' => 'giảm giá',
            'payment_method' => 'phương thức thanh toán',
            'note' => 'ghi chú',
            'items.*.name' => 'tên dòng dịch vụ',
            'items.*.quantity' => 'số lượng',
            'items.*.unit_price' => 'đơn giá',
            'items.*.commission_rate' => 'tỷ lệ hoa hồng',
        ];
    }
}
