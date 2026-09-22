<?php

namespace App\Services\Ai\Actions;

use App\Actions\Inventory\CompleteStockTransferAction;
use App\Actions\Inventory\RecordInventoryMovementAction;
use App\Enums\InventoryMovementType;
use App\Enums\StockTransferStatus;
use App\Models\BranchProduct;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\BranchContext;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Thao tác trên kho.
 *
 * Phiếu điều chỉnh giữ luật viết tay vì còn chặt hơn form quản trị một bậc:
 * vật tư phải nằm trong danh mục đang bật của chính chi nhánh đó, không chỉ
 * cần tồn tại trong danh mục chung.
 *
 * Tạo phiếu chuyển kho không nằm ở đây: một phiếu chuyển gồm nhiều dòng hàng,
 * nhập từng dòng trong khung chat khổ hơn mở thẳng màn hình kho.
 */
final class InventoryActions
{
    /** @return array<int, ActionDefinition> */
    public static function all(): array
    {
        return [
            self::adjustStock(),
            self::completeTransfer(),
            self::cancelTransfer(),
        ];
    }

    private static function adjustStock(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'adjust_stock',
            label: 'Điều chỉnh tồn kho',
            operation: 'update',
            resource: 'inventory',
            destructive: false,
            fields: fn (?int $branchId): array => [
                Field::select('product_id', 'Vật tư', fn (?int $id): array => Catalogue::products($id))->required(),
                Field::select('adjustment_mode', 'Cách điều chỉnh', [
                    'absolute' => 'Đặt lại số tồn',
                    'delta' => 'Cộng thêm hoặc trừ bớt',
                ])->default('absolute')->required(),
                Field::number('quantity', 'Số lượng')->required()->attributes(['step' => 'any']),
                Field::money('unit_cost', 'Đơn giá'),
                Field::datetime('occurred_at', 'Thời điểm')->default(now()->format('Y-m-d\TH:i'))->required(),
                Field::text('reference', 'Số chứng từ'),
                Field::textarea('note', 'Lý do điều chỉnh')->required()
                    ->help('Viết rõ vì sao lệch: kiểm kê, hàng hỏng, ghi nhầm…'),
                Field::fixed('type', 'Loại phiếu', InventoryMovementType::Adjustment->value),
            ],
            validator: Validation::inline(self::adjustmentRules(), [
                'branch_id' => 'chi nhánh',
                'product_id' => 'vật tư',
                'quantity' => 'số lượng',
                'note' => 'lý do',
                'occurred_at' => 'thời điểm',
            ]),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                Gate::forUser($actor)->authorize('create', InventoryMovement::class);

                return app(RecordInventoryMovementAction::class)->handle($payload, $actor);
            },
        );
    }

    private static function completeTransfer(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'complete_stock_transfer',
            label: 'Hoàn tất chuyển kho',
            operation: 'update',
            resource: 'stock_transfer',
            destructive: false,
            fields: fn (?int $branchId): array => [self::transferPicker()],
            validator: Validation::inline(
                ['stock_transfer_id' => ['required', 'integer']],
                ['stock_transfer_id' => 'phiếu chuyển kho'],
            ),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                Gate::forUser($actor)->authorize('complete', $subject);

                return app(CompleteStockTransferAction::class)->handle($subject, $actor);
            },
            subject: self::transferSubject(),
            branchScoped: false,
            hint: 'Ghi phiếu xuất ở kho gửi và phiếu nhập ở kho nhận.',
        );
    }

    private static function cancelTransfer(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'cancel_stock_transfer',
            label: 'Hủy phiếu chuyển kho',
            operation: 'delete',
            resource: 'stock_transfer',
            destructive: true,
            fields: fn (?int $branchId): array => [self::transferPicker()],
            validator: Validation::inline(
                ['stock_transfer_id' => ['required', 'integer']],
                ['stock_transfer_id' => 'phiếu chuyển kho'],
            ),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                Gate::forUser($actor)->authorize('cancel', $subject);

                $subject->forceFill([
                    'status' => StockTransferStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancelled_by' => $actor->getKey(),
                ])->save();

                return $subject;
            },
            subject: self::transferSubject(),
            branchScoped: false,
        );
    }

    private static function transferPicker(): Field
    {
        return Field::select('stock_transfer_id', 'Phiếu chuyển kho', fn (?int $id): array => Catalogue::stockTransfers($id))->required();
    }

    /**
     * Phiếu chuyển liên quan hai chi nhánh, nên chỉ cần một đầu nằm trong phạm
     * vi của người duyệt là họ có quyền nhìn thấy nó.
     */
    private static function transferSubject(): Closure
    {
        return Subject::of(
            StockTransfer::class,
            'stock_transfer_id',
            'phiếu chuyển kho',
            function (Builder $query): Builder {
                $scope = app(BranchContext::class)->scopeIds() ?: [0];

                return $query->where(fn (Builder $inner): Builder => $inner
                    ->whereIn('source_branch_id', $scope)
                    ->orWhereIn('destination_branch_id', $scope));
            },
        );
    }

    /** @return Closure(?int): array<string, array<int, mixed>> */
    private static function adjustmentRules(): Closure
    {
        return fn (?int $branchId): array => [
            'branch_id' => ['required', 'integer', Rule::in(app(BranchContext::class)->scopeIds())],
            'product_id' => [
                'required',
                'integer',
                Rule::exists(Product::class, 'id')->where('is_active', true),
                function (string $attribute, mixed $value, Closure $fail) use ($branchId): void {
                    $belongs = BranchProduct::query()
                        ->where('branch_id', $branchId)
                        ->where('product_id', (int) $value)
                        ->where('is_active', true)
                        ->exists();

                    if (! $belongs) {
                        $fail('Vật tư này không thuộc danh mục hoặc không còn hoạt động tại chi nhánh được chọn.');
                    }
                },
            ],
            'type' => ['required', Rule::in([InventoryMovementType::Adjustment->value])],
            'adjustment_mode' => ['required', Rule::in(['absolute', 'delta'])],
            'quantity' => ['required', 'numeric', 'min:-9999999', 'max:9999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['required', 'string', 'min:6', 'max:2000'],
            'occurred_at' => [
                'required', 'date',
                'before_or_equal:'.now()->endOfDay()->toDateTimeString(),
                'after_or_equal:'.now()->subDays((int) config('business.backdate_days'))->startOfDay()->toDateTimeString(),
            ],
        ];
    }
}
