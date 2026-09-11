<?php

namespace App\Actions\Inventory;

use App\Enums\AuditAction;
use App\Enums\InventoryMovementType;
use App\Models\BranchProduct;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Write a single stock movement at one branch.
 *
 * Quantities are handled as integer hundredths, the same scale as the
 * `decimal(12, 2)` column, so no float ever takes part in the balance maths.
 * The branch/product pairing row is locked for the whole transaction, which
 * serialises concurrent issues of the same product at the same shop and makes
 * the "no negative stock" rule safe against races. Stock at another branch is
 * never visible to this calculation.
 */
class RecordInventoryMovementAction
{
    public function __construct(private AuditRecorder $auditor) {}

    /**
     * @param  array{branch_id: int|string, product_id: int|string, type: string, quantity?: mixed, adjustment_mode?: string|null, supplier_id?: int|string|null, unit_cost?: mixed, reference?: string|null, note?: string|null, occurred_at?: mixed}  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data, User $actor): InventoryMovement
    {
        return DB::transaction(function () use ($data, $actor): InventoryMovement {
            $branchId = (int) $data['branch_id'];
            $product = Product::query()->findOrFail($data['product_id']);
            $type = InventoryMovementType::from($data['type']);

            $this->lockBranchStock($branchId, (int) $product->id);

            $currentMinor = self::branchStockMinor($branchId, (int) $product->id);
            $deltaMinor = $this->resolveDelta($type, $data, $currentMinor);

            if ($deltaMinor === 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Số lượng thay đổi phải khác 0.',
                ]);
            }

            if ($currentMinor + $deltaMinor < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Không đủ tồn kho tại chi nhánh này. Tồn hiện tại là '
                        .self::formatQuantity($currentMinor).' '.$product->unit.'.',
                ]);
            }

            $movement = InventoryMovement::query()->create([
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'supplier_id' => ($data['supplier_id'] ?? null) ?: null,
                'created_by' => $actor->id,
                'type' => $type,
                'quantity' => Money::toDecimal($deltaMinor),
                'unit_cost' => Money::toDecimal(abs(Money::toMinor($data['unit_cost'] ?? $product->cost_price))),
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);

            // Tồn trước và sau được ghi lại ngay tại đây vì chỉ ở trong lock
            // này con số mới chắc chắn đúng. Với phiếu điều chỉnh, đó chính là
            // bằng chứng để soát: đếm được bao nhiêu so với hệ thống đang ghi.
            $this->auditor->record(
                $movement,
                $actor,
                AuditAction::Created,
                null,
                [
                    'type' => $type->value,
                    'product_id' => (int) $product->id,
                    'product_name' => $product->name,
                    'quantity' => Money::toDecimal($deltaMinor),
                    'stock_before' => Money::toDecimal($currentMinor),
                    'stock_after' => Money::toDecimal($currentMinor + $deltaMinor),
                    'unit_cost' => (string) $movement->unit_cost,
                    'supplier_id' => $movement->supplier_id,
                    'reference' => $movement->reference,
                    'note' => $movement->note,
                ],
                $data['note'] ?? null,
                $branchId,
            );

            return $movement;
        });
    }

    /** Running balance of one product at one branch, in integer hundredths. */
    public static function branchStockMinor(int $branchId, int $productId): int
    {
        return Money::toMinor(
            InventoryMovement::query()
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->sum('quantity')
        );
    }

    public static function formatQuantity(int $minor): string
    {
        return rtrim(rtrim(Money::toDecimal($minor), '0'), '.') ?: '0';
    }

    /**
     * Take a row lock on the branch/product pairing.
     *
     * The pairing row is the natural mutex for "stock of this product at this
     * shop"; it is created on demand so a product that was never stocked here
     * still serialises correctly.
     */
    private function lockBranchStock(int $branchId, int $productId): void
    {
        BranchProduct::query()->firstOrCreate(
            ['branch_id' => $branchId, 'product_id' => $productId],
            ['minimum_stock' => 0, 'is_active' => true],
        );

        BranchProduct::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Translate the submitted quantity into a signed stock delta.
     *
     * Incoming and outgoing movements always take a positive quantity from the
     * form; outgoing rows are stored negative. Adjustments accept either the
     * counted stock ("absolute") or an already signed correction ("delta").
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveDelta(InventoryMovementType $type, array $data, int $currentMinor): int
    {
        $quantityMinor = Money::toMinor($data['quantity'] ?? 0);

        return match ($type) {
            InventoryMovementType::In => abs($quantityMinor),
            InventoryMovementType::Out => -abs($quantityMinor),
            InventoryMovementType::Adjustment => ($data['adjustment_mode'] ?? 'absolute') === 'delta'
                ? $quantityMinor
                : $quantityMinor - $currentMinor,
        };
    }
}
