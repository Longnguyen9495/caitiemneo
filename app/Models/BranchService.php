<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Price, duration and commission of one catalogue service at one branch.
 */
#[Fillable([
    'branch_id',
    'service_id',
    'price',
    'price_min',
    'price_max',
    'duration_minutes',
    'commission_rate',
    'overtime_commission_rate',
    'is_active',
])]
class BranchService extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_min' => 'decimal:2',
            'price_max' => 'decimal:2',
            'duration_minutes' => 'integer',
            'commission_rate' => 'decimal:2',
            'overtime_commission_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** Cơ sở này bán dịch vụ theo khoảng giá chứ không phải một mức cố định. */
    public function hasPriceRange(): bool
    {
        return $this->price_min !== null && $this->price_max !== null;
    }

    public function formattedPriceRange(): string
    {
        return Money::format($this->price_min).' – '.Money::format($this->price_max);
    }

    /**
     * Giá chốt có nằm trong khoảng của cơ sở này không.
     *
     * Dùng để cảnh báo khi lên hóa đơn, không phải để chặn: một ca khó bất
     * thường vẫn có thể vượt khoảng, và người quyết định là thợ chứ không
     * phải bảng giá.
     */
    public function priceIsWithinRange(int|float|string|null $price): bool
    {
        if (! $this->hasPriceRange() || $price === null || $price === '') {
            return true;
        }

        $minor = Money::toMinor($price);

        return $minor >= Money::toMinor($this->price_min)
            && $minor <= Money::toMinor($this->price_max);
    }
}
