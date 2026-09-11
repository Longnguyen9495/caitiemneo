<?php

namespace App\Models;

use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id',
    'invoice_id',
    'payroll_id',
    'reverses_transaction_id',
    'created_by',
    'type',
    'category',
    'amount',
    'payment_method',
    'reference',
    'idempotency_key',
    'note',
    'occurred_at',
    'voided_at',
    'voided_by',
    'void_reason',
])]
class CashTransaction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
            'voided_at' => 'datetime',
            'type' => CashTransactionType::class,
            'category' => CashTransactionCategory::class,
            'payment_method' => PaymentMethod::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function reversedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_transaction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** Transactions produced by another module must not be edited from the cash book. */
    public function isSystemGenerated(): bool
    {
        return $this->invoice_id !== null || $this->payroll_id !== null;
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    /**
     * The fields the audit trail compares before and after.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'type' => $this->type?->value,
            'category' => $this->category?->value,
            'amount' => (string) $this->amount,
            'payment_method' => $this->payment_method?->value,
            'reference' => $this->reference,
            'note' => $this->note,
            'occurred_at' => $this->occurred_at?->toDateTimeString(),
            'voided_at' => $this->voided_at?->toDateTimeString(),
            'void_reason' => $this->void_reason,
        ];
    }
}
