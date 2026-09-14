<?php

namespace App\Models;

use App\Enums\ShiftRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shift_request_id',
    'from_status',
    'to_status',
    'actor_id',
    'actor_name',
    'note',
])]
class ShiftRequestHistory extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'from_status' => ShiftRequestStatus::class,
            'to_status' => ShiftRequestStatus::class,
        ];
    }

    public function shiftRequest(): BelongsTo
    {
        return $this->belongsTo(ShiftRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
