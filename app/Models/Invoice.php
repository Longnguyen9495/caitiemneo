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
    'bill_image_path',
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

    /**
     * The money-bearing fields the audit trail compares before and after.
     *
     * Lines are included because a total is meaningless without knowing which
     * service, at which price, was credited to which member of staff.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status?->value,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'subtotal' => (string) $this->subtotal,
            'discount' => (string) $this->discount,
            'total' => (string) $this->total,
            'payment_method' => $this->payment_method?->value,
            'paid_at' => $this->paid_at?->toDateTimeString(),
            'cancelled_at' => $this->cancelled_at?->toDateTimeString(),
            'qualified_for_bill_kpi' => (bool) $this->qualified_for_bill_kpi,
            'note' => $this->note,
            'items' => $this->items->map(fn (InvoiceItem $item): array => [
                'id' => $item->getKey(),
                'name' => $item->name,
                'service_id' => $item->service_id,
                'employee_id' => $item->employee_id,
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'line_total' => (string) $item->line_total,
                'commission_rate' => (string) $item->commission_rate,
                'commission_amount' => (string) $item->commission_amount,
                'work_context' => $item->work_context?->value,
            ])->values()->all(),
        ];
    }
}
