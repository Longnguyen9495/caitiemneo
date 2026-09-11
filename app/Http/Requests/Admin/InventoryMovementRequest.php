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
            // A retired product or supplier must not receive new movements:
            // that is how stock is quietly parked against a dead record.
            'product_id' => ['required', 'integer', Rule::exists(Product::class, 'id')->where('is_active', true)],
            'supplier_id' => ['nullable', 'integer', Rule::exists(Supplier::class, 'id')->where('is_active', true)],
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
            // Phiếu điều chỉnh là loại duy nhất giảm được tồn chỉ bằng một lý
            // do gõ tay, nên lý do phải nói được điều gì đó. Ngưỡng cố ý thấp:
            // "Hỏng hàng" là lý do thật và đủ rõ, chỉ chặn kiểu gõ cho có.
            'note' => $isAdjustment
                ? ['required', 'string', 'min:6', 'max:2000']
                : ['nullable', 'string', 'max:2000'],
            'occurred_at' => [
                'required', 'date',
                'before_or_equal:'.now()->endOfDay()->toDateTimeString(),
                'after_or_equal:'.now()->subDays((int) config('business.backdate_days'))->startOfDay()->toDateTimeString(),
            ],
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
            'note.min' => 'Lý do điều chỉnh cần nói rõ hơn, ít nhất 6 ký tự.',
            'quantity.gt' => 'Số lượng nhập hoặc xuất phải lớn hơn 0.',
            'occurred_at.before_or_equal' => 'Không ghi được phiếu kho của ngày trong tương lai.',
            'occurred_at.after_or_equal' => 'Phiếu kho ghi lùi quá '.config('business.backdate_days').' ngày cần xử lý qua phiếu điều chỉnh.',
        ];
    }
}
