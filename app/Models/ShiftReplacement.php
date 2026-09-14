<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shift_request_id',
    'original_shift_assignment_id',
    'replacement_employee_id',
    'replacement_shift_assignment_id',
    'assigned_by',
    'assigned_at',
])]
class ShiftReplacement extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }

    public function shiftRequest(): BelongsTo
    {
        return $this->belongsTo(ShiftRequest::class);
    }

    public function originalShiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'original_shift_assignment_id');
    }

    public function replacementEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replacement_employee_id');
    }

    public function replacementShiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'replacement_shift_assignment_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
