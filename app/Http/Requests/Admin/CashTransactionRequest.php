<?php

namespace App\Http\Requests\Admin;

use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\ResolvesBranch;
use App\Models\CashTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashTransactionRequest extends FormRequest
{
    use ResolvesBranch;

    public function authorize(): bool
    {
        $transaction = $this->route('cash_transaction');

        return $transaction instanceof CashTransaction
            ? $this->user()->can('update', $transaction)
            : $this->user()->can('create', CashTransaction::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => $this->branchRules(),
            'type' => ['required', Rule::enum(CashTransactionType::class)],
            'category' => [
                'required',
                Rule::in(array_keys(CashTransactionCategory::manualOptions())),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            // Giao dịch ghi ngày tương lai thì chưa xảy ra; ghi lùi quá ngưỡng
            // là cách đẩy một con số vào kỳ sổ sách đã chốt.
            'occurred_at' => [
                'required', 'date',
                'before_or_equal:'.now()->endOfDay()->toDateTimeString(),
                'after_or_equal:'.now()->subDays((int) config('business.backdate_days'))->startOfDay()->toDateTimeString(),
            ],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $transaction = $this->route('cash_transaction');

        // A booked entry stays in the branch whose cash box it belongs to.
        $this->merge([
            'branch_id' => $transaction instanceof CashTransaction
                ? $transaction->branch_id
                : $this->contextBranchId(),
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'branch_id' => 'chi nhánh',
            'type' => 'loại giao dịch',
            'category' => 'hạng mục',
            'amount' => 'số tiền',
            'payment_method' => 'phương thức',
            'occurred_at' => 'thời điểm',
            'reference' => 'tham chiếu',
            'note' => 'ghi chú',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->branchMessages() + [
            'category.in' => 'Hạng mục này chỉ được sinh tự động từ hóa đơn hoặc bảng lương.',
            'amount.gt' => 'Số tiền phải lớn hơn 0. Chiều tiền do loại giao dịch quyết định.',
            'occurred_at.before_or_equal' => 'Không ghi được giao dịch của ngày trong tương lai.',
            'occurred_at.after_or_equal' => 'Giao dịch ghi lùi quá '.config('business.backdate_days').' ngày phải xử lý qua phiếu điều chỉnh có người duyệt.',
        ];
    }
}
