<?php

namespace App\Queries;

use App\Models\CashTransaction;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CashTransactionQuery
{
    use BranchScope;

    public function __construct(private BranchContext $branchContext) {}

    /** @return Builder<CashTransaction> */
    public function build(Request $request): Builder
    {
        return $this->scopeToBranches(CashTransaction::query(), $this->branchContext)
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';

                $query->where(fn (Builder $inner) => $inner
                    ->where('reference', 'like', $term)
                    ->orWhere('note', 'like', $term));
            })
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->string('category')->toString()))
            ->when($request->filled('payment_method'), fn (Builder $query) => $query->where('payment_method', $request->string('payment_method')->toString()))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('occurred_at', '<=', $request->date('to')))
            ->unless($request->boolean('include_voided'), fn (Builder $query) => $query->active());
    }
}
