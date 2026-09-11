<?php

namespace App\Models;

use App\Enums\ServiceCategory;
use App\Enums\ServiceUnit;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một dòng trong bảng giá của tiệm.
 *
 * `price` là giá mặc định điền sẵn lên hóa đơn. Với dịch vụ tính theo độ khó
 * (vẽ móng, charm đá, phá gel…) thì `price_min`/`price_max` mô tả khoảng thợ
 * được chốt; để trống nghĩa là giá cố định.
 */
#[Fillable([
    'name',
    'description',
    'category',
    'unit',
    'price',
    'price_min',
    'price_max',
    'display_order',
    'is_active',
])]
class Service extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'unit' => ServiceUnit::class,
            'price' => 'decimal:2',
            'price_min' => 'decimal:2',
            'price_max' => 'decimal:2',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected function formattedPrice(): Attribute
    {
        return Attribute::get(fn (): string => $this->hasPriceRange()
            ? $this->formattedPriceRange()
            : Money::format($this->price));
    }

    public function appointmentServices(): HasMany
    {
        return $this->hasMany(AppointmentService::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function branchServices(): HasMany
    {
        return $this->hasMany(BranchService::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Thứ tự đúng như trên bảng giá giấy: theo nhóm, rồi theo vị trí trong nhóm.
     *
     * Dùng CASE thay cho `orderBy('category')` vì sắp theo chuỗi sẽ cho ra
     * Trang trí đứng trước Nối móng. Viết bằng binding nên chạy được cả trên
     * MySQL lẫn SQLite.
     */
    public function scopeInMenuOrder(Builder $query): Builder
    {
        $cases = ServiceCategory::cases();
        $sql = 'case '.str_repeat('when category = ? then ? ', count($cases)).'else ? end';

        $bindings = [];

        foreach ($cases as $case) {
            $bindings[] = $case->value;
            $bindings[] = $case->sortOrder();
        }

        $bindings[] = count($cases);

        return $query
            ->orderByRaw($sql, $bindings)
            ->orderBy('display_order')
            ->orderBy('name');
    }

    public function scopeInCategory(Builder $query, ServiceCategory|string $category): Builder
    {
        return $query->where('category', $category instanceof ServiceCategory ? $category->value : $category);
    }

    /** Dịch vụ chốt giá theo độ khó, không phải giá cố định. */
    public function hasPriceRange(): bool
    {
        return $this->price_min !== null && $this->price_max !== null;
    }

    public function formattedPriceRange(): string
    {
        return Money::format($this->price_min).' – '.Money::format($this->price_max);
    }

    /**
     * Một mức giá có nằm trong khoảng cho phép không.
     *
     * Giá cố định thì mọi giá trị đều được coi là hợp lệ: đây là cảnh báo giúp
     * thợ tránh gõ nhầm, không phải rào chặn nghiệp vụ.
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

    /** Nhãn đơn vị đặt cạnh ô số lượng, ví dụ "Số ngón". */
    public function quantityLabel(): string
    {
        return ($this->unit ?? ServiceUnit::Set)->quantityLabel();
    }
}
