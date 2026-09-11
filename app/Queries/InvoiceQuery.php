<?php

namespace App\Queries;

use App\Models\Invoice;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filter pipeline shared by the invoice list screen and the CSV export, so both
 * always describe exactly the same set of rows.
 */
class InvoiceQuery
{
    use BranchScope;

    public function __construct(private BranchContext $branchContext) {}

    /** @return Builder<Invoice> */
    public function build(Request $request): Builder
    {
        return $this->scopeToBranches(Invoice::query(), $this->branchContext)
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';

                $query->where(fn (Builder $inner) => $inner
                    ->where('number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term));
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('payment_method'), fn (Builder $query) => $query->where('payment_method', $request->string('payment_method')->toString()))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('to')));
    }
}
