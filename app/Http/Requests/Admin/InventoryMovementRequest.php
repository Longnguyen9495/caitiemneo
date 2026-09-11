<?php

namespace App\Http\Requests\Admin;

use App\Enums\InventoryMovementType;
use App\Http\Requests\Concerns\ResolvesBranch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryMovementRequest extends FormRequest
{
    use ResolvesBranch;

    public function authorize(): bool
    {
        return $this->user()->can('create', InventoryMovement::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $isAdjustment = $this->input('type') === InventoryMovementType::Adjustment->value;

        return [
            'branch_id' => $this->branchRules(),
            'product_id' => ['required', Rule::exists(Product::class, 'id')],
            'supplier_id' => ['nullable', Rule::exists(Supplier::class, 'id')],
            'type' => ['required', Rule::enum(InventoryMovementType::class)],
            'adjustment_mode' => [
                Rule::requiredIf($isAdjustment),
                Rule::in(['absolute', 'delta']),
            ],
            'quantity' => [
                'required',
                'numeric',
                $isAdjustment ? 'min:-9999999' : 'gt:0',
                'max:9999999',
            ],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => [$isAdjustment ? 'required' : 'nullable', 'string', 'max:2000'],
            'occurred_at' => ['required', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeResolvedBranch();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'branch_id' => 'chi nhánh',
            'product_id' => 'vật tư',
            'supplier_id' => 'nhà cung cấp',
            'type' => 'loại phiếu',
            'adjustment_mode' => 'kiểu điều chỉnh',
            'quantity' => 'số lượng',
            'unit_cost' => 'đơn giá',
            'reference' => 'tham chiếu',
            'note' => 'ghi chú',
            'occurred_at' => 'thời điểm',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->branchMessages() + [
            'note.required' => 'Phiếu điều chỉnh bắt buộc phải ghi lý do.',
            'quantity.gt' => 'Số lượng nhập hoặc xuất phải lớn hơn 0.',
        ];
    }
}
