<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'branch_id',
    'customer_id',
    'employee_id',
    'gallery_item_id',
    'customer_name',
    'customer_phone',
    'customer_email',
    'starts_at',
    'ends_at',
    'duration_minutes',
    'status',
    'note',
])]
class Appointment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'duration_minutes' => 'integer',
            'status' => AppointmentStatus::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Mẫu móng khách chọn trong album lúc đặt lịch online.
     *
     * Rỗng với lịch hẹn tạo tay trong khu quản trị và với lịch khách đặt mà
     * không trỏ vào mẫu nào.
     */
    public function galleryItem(): BelongsTo
    {
        return $this->belongsTo(GalleryItem::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(AppointmentService::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /** Appointments that still hold a slot on the employee calendar. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', AppointmentStatus::blockingValues());
    }

    /**
     * Appointments of one employee overlapping the given half-open window.
     *
     * The predicate is plain SQL comparison so it behaves identically on SQLite
     * and MySQL, unlike the previous `datetime(..., '+' || ... )` expression.
     */
    public function scopeOverlapping(Builder $query, mixed $startsAt, mixed $endsAt): Builder
    {
        return $query->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
    }

    /** @return array<string, mixed> */
    public function auditSnapshot(): array
    {
        return [
            'branch_id' => $this->branch_id,
            'customer_id' => $this->customer_id,
            'employee_id' => $this->employee_id,
            'gallery_item_id' => $this->gallery_item_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status?->value,
            'note' => $this->note,
            'service_ids' => $this->services->pluck('service_id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
        ];
    }
}
