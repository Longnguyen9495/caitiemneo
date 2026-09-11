<?php

namespace App\Models;

use App\Enums\StockTransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'source_branch_id',
    'destination_branch_id',
    'status',
    'transferred_at',
    'created_by',
    'completed_by',
    'cancelled_by',
    'cancelled_at',
    'note',
])]
class StockTransfer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => StockTransferStatus::class,
            'transferred_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isEditable(): bool
    {
        return $this->status === StockTransferStatus::Draft;
    }

    /** Transfers touching either side of the given branches. */
    public function scopeForBranches(Builder $query, array $branchIds): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->whereIn('source_branch_id', $branchIds)
            ->orWhereIn('destination_branch_id', $branchIds));
    }

    /**
     * Trạng thái phiếu ở hai phía của một thay đổi, cho nhật ký audit.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status?->value,
            'source_branch_id' => $this->source_branch_id,
            'destination_branch_id' => $this->destination_branch_id,
            'created_by' => $this->created_by,
            'completed_by' => $this->completed_by,
            'transferred_at' => $this->transferred_at?->toDateTimeString(),
            'note' => $this->note,
            'items' => $this->items->map(fn (StockTransferItem $item): array => [
                'product_id' => $item->product_id,
                'quantity' => (string) $item->quantity,
                'unit_cost' => (string) $item->unit_cost,
            ])->values()->all(),
        ];
    }
}
