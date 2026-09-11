<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\StockTransferStatus;
use App\Models\BranchProduct;
use App\Models\InventoryMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Move stock from one shop to another.
 *
 * Completion writes exactly two movements per line: an outgoing one at the
 * source and an incoming one at the destination. Both live inside one
 * transaction, and the transfer row is locked first, so completing the same
 * transfer twice returns the already completed record instead of duplicating
 * stock.
 */
class CompleteStockTransferAction
{
    /** @throws ValidationException */
    public function handle(StockTransfer $transfer, User $actor): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $actor): StockTransfer {
            $locked = StockTransfer::query()
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($transfer->getKey());

            if ($locked->status === StockTransferStatus::Completed) {
                return $locked;
            }

            if ($locked->status !== StockTransferStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ phiếu chuyển kho ở trạng thái nháp mới có thể hoàn tất.',
                ]);
            }

            if ($locked->source_branch_id === $locked->destination_branch_id) {
                throw ValidationException::withMessages([
                    'destination_branch_id' => 'Chi nhánh nhận phải khác chi nhánh gửi.',
                ]);
            }

            if ($locked->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Phiếu chuyển kho phải có ít nhất một dòng vật tư.',
                ]);
            }

            $this->lockStockRows($locked);
            $this->guardSourceStock($locked);

            $transferredAt = now();

            foreach ($locked->items as $item) {
                $quantityMinor = abs(Money::toMinor($item->quantity));

                $this->writeMovement($locked, $item->product_id, $locked->source_branch_id, InventoryMovementType::Out, -$quantityMinor, $item->unit_cost, $transferredAt);
                $this->writeMovement($locked, $item->product_id, $locked->destination_branch_id, InventoryMovementType::In, $quantityMinor, $item->unit_cost, $transferredAt);
            }

            $locked->forceFill([
                'status' => StockTransferStatus::Completed,
                'transferred_at' => $transferredAt,
                'completed_by' => $actor->getKey(),
            ])->save();

            return $locked;
        });
    }

    /**
     * Lock every branch/product pairing this transfer touches, in a stable id
     * order, so two transfers running at once cannot deadlock each other.
     */
    private function lockStockRows(StockTransfer $transfer): void
    {
        $productIds = $transfer->items->pluck('product_id')->unique()->sort()->values();
        $branchIds = collect([$transfer->source_branch_id, $transfer->destination_branch_id])->sort()->values();

        foreach ($branchIds as $branchId) {
            foreach ($productIds as $productId) {
                BranchProduct::query()->firstOrCreate(
                    ['branch_id' => $branchId, 'product_id' => $productId],
                    ['minimum_stock' => 0, 'is_active' => true],
                );
            }
        }

        BranchProduct::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('product_id', $productIds)
            ->orderBy('branch_id')
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get();
    }

    /** @throws ValidationException */
    private function guardSourceStock(StockTransfer $transfer): void
    {
        foreach ($transfer->items as $item) {
            $available = RecordInventoryMovementAction::branchStockMinor($transfer->source_branch_id, (int) $item->product_id);
            $requested = abs(Money::toMinor($item->quantity));

            if ($requested > $available) {
                throw ValidationException::withMessages([
                    'items' => 'Vật tư "'.($item->product?->name ?? '#'.$item->product_id).'" chỉ còn '
                        .RecordInventoryMovementAction::formatQuantity($available).' tại chi nhánh gửi.',
                ]);
            }
        }
    }

    private function writeMovement(
        StockTransfer $transfer,
        int $productId,
        int $branchId,
        InventoryMovementType $type,
        int $quantityMinor,
        mixed $unitCost,
        mixed $occurredAt,
    ): void {
        InventoryMovement::query()->create([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'created_by' => $transfer->completed_by ?? $transfer->created_by,
            'type' => $type,
            'quantity' => Money::toDecimal($quantityMinor),
            'unit_cost' => $unitCost,
            'reference' => $transfer->number,
            'note' => $type === InventoryMovementType::Out
                ? 'Chuyển kho đi theo phiếu '.$transfer->number
                : 'Nhận chuyển kho theo phiếu '.$transfer->number,
            'occurred_at' => $occurredAt,
        ]);
    }
}
