<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dated posting of one employee to one branch.
 *
 * This table, not `users`, is the history of record: an assignment question is
 * always asked about a business date, never about "now".
 */
#[Fillable(['branch_id', 'user_id', 'is_primary', 'starts_on', 'ends_on', 'created_by'])]
class EmployeeBranchAssignment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Assignments covering a given business date.
     *
     * An open-ended posting (`ends_on` is null) covers every date from its
     * start onwards.
     */
    public function scopeCovering(Builder $query, mixed $date): Builder
    {
        return $query
            ->whereDate('starts_on', '<=', $date)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('ends_on')
                ->orWhereDate('ends_on', '>=', $date));
    }

    /** Assignments whose window overlaps the given closed or open range. */
    public function scopeOverlappingWindow(Builder $query, mixed $startsOn, mixed $endsOn = null): Builder
    {
        return $query
            ->where(fn (Builder $inner) => $inner
                ->whereNull('ends_on')
                ->orWhereDate('ends_on', '>=', $startsOn))
            ->when($endsOn !== null, fn (Builder $inner) => $inner->whereDate('starts_on', '<=', $endsOn));
    }
}
