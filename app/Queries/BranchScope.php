<?php

namespace App\Queries;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies the active branch selection to a list query.
 *
 * Kept as an explicit helper rather than a global scope so that reports,
 * console commands and tests can opt out deliberately instead of by accident.
 */
trait BranchScope
{
    /**
     * Limit a query to the branches the current request may see.
     *
     * An empty scope means the user may see nothing, which is expressed as a
     * query that matches no rows rather than one that matches everything.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    protected function scopeToBranches(Builder $query, BranchContext $context, string $column = 'branch_id'): Builder
    {
        $branchIds = $context->scopeIds();

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($query->qualifyColumn($column), $branchIds);
    }
}
