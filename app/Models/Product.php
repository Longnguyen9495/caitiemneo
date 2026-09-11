<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'sku', 'unit', 'cost_price', 'minimum_stock', 'is_active'])]
class Product extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'minimum_stock' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Stock is the running balance of every movement row.
     *
     * The value is read from the `stock_balance` aggregate added by
     * {@see scopeWithCurrentStock()}. It is never summed per row inside the
     * accessor, so listing products stays a single query.
     */
    protected function currentStock(): Attribute
    {
        return Attribute::get(function (): string {
            if (array_key_exists('stock_balance', $this->attributes)) {
                return number_format((float) ($this->attributes['stock_balance'] ?? 0), 2, '.', '');
            }

            return number_format((float) $this->movements()->sum('quantity'), 2, '.', '');
        });
    }

    /** The minimum this branch wants on hand, falling back to the global default. */
    protected function effectiveMinimumStock(): Attribute
    {
        return Attribute::get(fn (): string => number_format(
            (float) ($this->attributes['branch_minimum_stock'] ?? $this->attributes['minimum_stock'] ?? 0),
            2,
            '.',
            ''
        ));
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function branchProducts(): HasMany
    {
        return $this->hasMany(BranchProduct::class);
    }

    /**
     * Add the running stock balance as a single correlated subquery.
     *
     * Passing a branch limits the balance to that shop; stock held elsewhere is
     * invisible, which is exactly what an issue or a transfer has to see.
     */
    public function scopeWithCurrentStock(Builder $query, ?int $branchId = null): Builder
    {
        return $query->withSum(
            ['movements as stock_balance' => fn ($movements) => $branchId === null
                ? $movements
                : $movements->where('branch_id', $branchId)],
            'quantity'
        );
    }

    /** Add the branch-specific minimum stock, when one is configured. */
    public function scopeWithBranchMinimum(Builder $query, ?int $branchId): Builder
    {
        if ($branchId === null) {
            return $query;
        }

        return $query->addSelect([
            'branch_minimum_stock' => BranchProduct::query()
                ->selectRaw('minimum_stock')
                ->whereColumn('branch_products.product_id', 'products.id')
                ->where('branch_products.branch_id', $branchId)
                ->limit(1),
        ]);
    }

    /**
     * Products at or below their minimum, evaluated by the database.
     *
     * With a branch the comparison uses that branch's balance and its own
     * threshold; without one it falls back to the company-wide figures.
     */
    public function scopeLowStock(Builder $query, ?int $branchId = null): Builder
    {
        if ($branchId === null) {
            return $query->whereRaw(
                '(select coalesce(sum(quantity), 0) from inventory_movements where inventory_movements.product_id = products.id) <= products.minimum_stock'
            );
        }

        return $query->whereRaw(
            '(select coalesce(sum(quantity), 0) from inventory_movements'
            .' where inventory_movements.product_id = products.id and inventory_movements.branch_id = ?)'
            .' <= coalesce((select minimum_stock from branch_products'
            .' where branch_products.product_id = products.id and branch_products.branch_id = ?), products.minimum_stock)',
            [$branchId, $branchId]
        );
    }

    /** Products the given branch actually stocks. */
    public function scopeStockedAt(Builder $query, ?int $branchId): Builder
    {
        if ($branchId === null) {
            return $query;
        }

        return $query->whereHas('branchProducts', fn (Builder $inner) => $inner
            ->where('branch_id', $branchId)
            ->where('is_active', true));
    }
}
