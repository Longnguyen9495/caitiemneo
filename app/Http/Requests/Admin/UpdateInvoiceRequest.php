<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Enums\WorkContext;
use App\Models\BranchService;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Rules\AssignedToBranch;
use App\Rules\InBranchCatalogue;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invoice'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $branchId = $invoice instanceof Invoice ? $invoice->branch_id : null;

        // Commission is attributed as of the day the invoice was opened, so the
        // posting is checked against that date rather than against "now".
        $referenceDate = $invoice instanceof Invoice
            ? ($invoice->created_at ?? now())
            : now();

        return [
            // Phiên bản của bản ghi lúc mở biểu mẫu. Không bắt buộc: màn hình
            // nào chưa gửi thì vẫn chạy như cũ, chặn hết sẽ làm hỏng luồng đang
            // dùng được để phòng một vấn đề hiếm hơn.
            'expected_version' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'discount' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'payment_method' => ['nullable', Rule::in(array_keys(PaymentMethod::invoiceOptions()))],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array', 'max:100'],
            // A line id from another invoice must not be adoptable by this one.
            'items.*.id' => [
                'nullable', 'integer',
                Rule::exists(InvoiceItem::class, 'id')->where(
                    'invoice_id',
                    $invoice instanceof Invoice ? $invoice->getKey() : 0,
                ),
            ],
            'items.*.service_id' => ['nullable', 'integer', new InBranchCatalogue($branchId)],
            'items.*.employee_id' => ['nullable', 'integer', new AssignedToBranch($branchId, $referenceDate)],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01', 'max:9999'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0', 'max:99999999999'],
            'items.*.commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.commission_rate_reason' => ['nullable', 'string', 'max:255'],
            'items.*.price_override_reason' => ['nullable', 'string', 'min:10', 'max:255'],
            'items.*.work_context' => ['nullable', Rule::enum(WorkContext::class)],
        ];
    }

    /**
     * The two decisions an operator must not take alone: pricing a line outside
     * the branch range, and discounting beyond the configured threshold.
     *
     * Both are checked here rather than in a rule object because each needs to
     * see the whole payload — the discount against the subtotal the lines add
     * up to, and the price against the catalogue entry for that line's service.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $invoice = $this->route('invoice');

            // Các kiểm tra dưới đây đọc số tiền và số lượng trực tiếp từ payload,
            // nên chỉ chạy khi những trường đó đã qua được rule kiểu dữ liệu.
            // Nếu không, một chữ "abc" trong ô giảm giá sẽ làm Money::toMinor()
            // ném lỗi và người dùng nhận về trang lỗi 500 thay vì thông báo.
            if (! $invoice instanceof Invoice || $validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateNobodyElseSavedFirst($validator, $invoice);
            $this->validatePriceOverrides($validator, $invoice);
            $this->validateDiscount($validator, $invoice);
        });
    }

    /**
     * Từ chối một lần lưu dựng trên phiên bản đã cũ.
     *
     * Không có bước này thì người bấm sau xóa mất công của người bấm trước mà
     * cả hai đều không biết: trên hóa đơn nghĩa là một khoản giảm giá hoặc một
     * dòng dịch vụ lặng lẽ quay về giá trị cũ sau khi đã thống nhất với khách.
     */
    private function validateNobodyElseSavedFirst(Validator $validator, Invoice $invoice): void
    {
        $expected = $this->input('expected_version');

        if ($expected === null || $expected === '') {
            return;
        }

        if ((int) $expected !== (int) $invoice->updated_at?->timestamp) {
            $validator->errors()->add(
                'expected_version',
                'Hóa đơn này vừa được người khác lưu. Hãy tải lại trang để xem thay đổi mới nhất rồi nhập lại.',
            );
        }
    }

    private function validatePriceOverrides(Validator $validator, Invoice $invoice): void
    {
        $items = (array) $this->input('items', []);
        $serviceIds = array_filter(array_column($items, 'service_id'));

        if ($serviceIds === []) {
            return;
        }

        $catalogue = BranchService::query()
            ->where('branch_id', $invoice->branch_id)
            ->whereIn('service_id', $serviceIds)
            ->get()
            ->keyBy('service_id');

        $mayOverride = $this->user()->can('overridePrice', $invoice);

        foreach ($items as $index => $row) {
            $branchService = $catalogue->get((int) ($row['service_id'] ?? 0));

            if ($branchService === null || $branchService->priceIsWithinRange($row['unit_price'] ?? null)) {
                continue;
            }

            if (! $mayOverride) {
                $validator->errors()->add(
                    "items.{$index}.unit_price",
                    'Giá này nằm ngoài khoảng '.$branchService->formattedPriceRange().'. Hãy nhờ quản lý duyệt.',
                );

                continue;
            }

            if (trim((string) ($row['price_override_reason'] ?? '')) === '') {
                $validator->errors()->add(
                    "items.{$index}.price_override_reason",
                    'Giá ngoài khoảng '.$branchService->formattedPriceRange().' cần ghi lý do để lưu vào nhật ký.',
                );
            }
        }
    }

    private function validateDiscount(Validator $validator, Invoice $invoice): void
    {
        $discountMinor = Money::toMinor($this->input('discount', 0));

        if ($discountMinor <= 0 || $this->user()->can('applyLargeDiscount', $invoice)) {
            return;
        }

        $subtotalMinor = 0;

        foreach ((array) $this->input('items', []) as $row) {
            $subtotalMinor += (int) round(
                Money::toMinor($row['unit_price'] ?? 0) * (float) ($row['quantity'] ?? 0)
            );
        }

        // Whichever limit bites first: a percentage of the bill, or a flat cap.
        $percentLimit = (int) round($subtotalMinor * (float) config('business.operator_discount_percent') / 100);
        $absoluteLimit = Money::toMinor(config('business.operator_discount_max'));
        $limit = min($percentLimit, $absoluteLimit);

        if ($discountMinor > $limit) {
            $validator->errors()->add(
                'discount',
                'Mức giảm này vượt quyền của bạn (tối đa '.Money::format(Money::toDecimal($limit)).'). Hãy nhờ quản lý duyệt.',
            );
        }
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
            'items.*.price_override_reason' => 'lý do giá ngoài khoảng',
        ];
    }
}
