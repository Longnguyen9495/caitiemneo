<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'branch_id',
    'number',
    'appointment_id',
    'customer_id',
    'created_by',
    'customer_name',
    'customer_phone',
    'status',
    'payment_method',
    'subtotal',
    'discount',
    'total',
    'paid_at',
    'cancelled_at',
    'cancelled_by',
    'cancel_reason',
    'qualified_for_bill_kpi',
    'bill_kpi_verified_by',
    'bill_kpi_verified_at',
    'bill_kpi_note',
    'note',
])]
class Invoice extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'bill_kpi_verified_at' => 'datetime',
            'qualified_for_bill_kpi' => 'boolean',
            'status' => InvoiceStatus::class,
            'payment_method' => PaymentMethod::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function billKpiVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bill_kpi_verified_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Paid);
    }

    /** Revenue is always reported on `paid_at`, never on `created_at`. */
    public function scopePaidBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query->paid()->whereBetween('paid_at', [$from, $to]);
    }
}
