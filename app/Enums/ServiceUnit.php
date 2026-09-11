<?php

namespace App\Enums;

/**
 * Đơn vị tính của một dịch vụ.
 *
 * Bảng giá của tiệm chia rõ "giá theo bộ" và "giá theo ngón". Không có đơn vị
 * thì con số `quantity` trên hóa đơn là vô nghĩa: 3 bộ và 3 ngón chênh nhau
 * rất xa về tiền.
 */
enum ServiceUnit: string
{
    case Set = 'set';
    case Finger = 'finger';

    public function label(): string
    {
        return match ($this) {
            self::Set => 'Bộ',
            self::Finger => 'Ngón',
        };
    }

    /** Nhãn đặt cạnh ô số lượng trên hóa đơn. */
    public function quantityLabel(): string
    {
        return match ($this) {
            self::Set => 'Số bộ',
            self::Finger => 'Số ngón',
        };
    }

    public function priceNote(): string
    {
        return match ($this) {
            self::Set => 'Giá theo bộ',
            self::Finger => 'Giá theo ngón',
        };
    }

    /** Một bàn tay mười ngón: quá số này gần như chắc chắn là gõ nhầm. */
    public function maximumQuantity(): int
    {
        return $this === self::Finger ? 20 : 10;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
