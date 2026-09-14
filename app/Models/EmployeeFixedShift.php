<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'branch_id',
    'work_shift_id',
    'effective_from',
    'effective_to',
    'created_by',
])]
class EmployeeFixedShift extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeEffectiveOn(Builder $query, mixed $date): Builder
    {
        return $query
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $date));
    }
}
