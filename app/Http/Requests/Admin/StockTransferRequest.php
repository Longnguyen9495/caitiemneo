<?php

namespace App\Http\Requests\Admin;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StockTransfer::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        // Stock may only be sent out of a branch the user is actually posted to.
        $allowedSources = app(BranchContext::class)->available()->pluck('id')->all();

        return [
            'source_branch_id' => ['required', 'integer', Rule::in($allowedSources)],
            'destination_branch_id' => [
                'required', 'integer', 'different:source_branch_id',
                Rule::exists(Branch::class, 'id')->where('is_active', true),
            ],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', Rule::exists(Product::class, 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $productIds = array_column($this->input('items', []), 'product_id');

            if (count($productIds) !== count(array_unique($productIds))) {
                $validator->errors()->add('items', 'Mỗi vật tư chỉ được xuất hiện một lần trong phiếu.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'source_branch_id.in' => 'Bạn không có quyền xuất kho từ chi nhánh này.',
            'destination_branch_id.different' => 'Chi nhánh nhận phải khác chi nhánh gửi.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'source_branch_id' => 'chi nhánh gửi',
            'destination_branch_id' => 'chi nhánh nhận',
            'items' => 'danh sách vật tư',
            'items.*.quantity' => 'số lượng',
        ];
    }
}
