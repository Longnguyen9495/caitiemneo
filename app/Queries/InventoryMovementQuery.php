<?php

namespace App\Queries;

use App\Models\InventoryMovement;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class InventoryMovementQuery
{
    use BranchScope;

    public function __construct(private BranchContext $branchContext) {}

    /** @return Builder<InventoryMovement> */
    public function build(Request $request): Builder
    {
        return $this->scopeToBranches(InventoryMovement::query(), $this->branchContext)
            ->when($request->filled('product_id'), fn (Builder $query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('supplier_id'), fn (Builder $query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('reference'), fn (Builder $query) => $query->where('reference', 'like', '%'.$request->string('reference')->toString().'%'))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('occurred_at', '<=', $request->date('to')));
    }
}
