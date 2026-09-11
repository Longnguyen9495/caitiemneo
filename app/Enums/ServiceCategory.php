<?php

namespace App\Enums;

/**
 * Nhóm dịch vụ theo đúng cách bảng giá của tiệm chia.
 *
 * Nhóm quyết định thứ tự hiển thị khi thợ chọn dịch vụ lên hóa đơn, nên thứ tự
 * các case ở đây chính là thứ tự trên menu giấy.
 */
enum ServiceCategory: string
{
    case BasicNail = 'basic_nail';
    case Extension = 'extension';
    case Decoration = 'decoration';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BasicNail => 'Nail cơ bản',
            self::Extension => 'Nối móng',
            self::Decoration => 'Trang trí',
            self::Other => 'Khác',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::BasicNail => 'is-active',
            self::Extension => 'is-success',
            self::Decoration => 'is-warning',
            self::Other => 'is-muted',
        };
    }

    /**
     * Vị trí của nhóm trên bảng giá.
     *
     * Sắp theo tên nhóm sẽ cho ra Trang trí đứng trước Nối móng, không khớp
     * menu giấy; thứ tự khai báo case ở trên mới là thứ tự đúng.
     */
    public function sortOrder(): int
    {
        return array_search($this, self::cases(), true);
    }

    /** Đơn vị tính mặc định của nhóm, dùng làm gợi ý khi thêm dịch vụ mới. */
    public function defaultUnit(): ServiceUnit
    {
        return $this === self::Decoration ? ServiceUnit::Finger : ServiceUnit::Set;
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
