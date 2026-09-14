<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'branch_id',
    'leave_date',
    'scheduled_by',
])]
class MonthlyPaidLeaveDay extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['leave_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function scopeInMonth(Builder $query, mixed $month): Builder
    {
        $start = CarbonImmutable::parse($month)->startOfMonth();

        return $query->whereBetween('leave_date', [$start->toDateString(), $start->endOfMonth()->toDateString()]);
    }
}
