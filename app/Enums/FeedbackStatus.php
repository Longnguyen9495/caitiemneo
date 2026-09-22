<?php

namespace App\Enums;

/**
 * Trạng thái của một feedback khách gửi lên.
 *
 * Mọi feedback bắt đầu ở `Pending`: trang chủ là mặt tiền của tiệm, nên không
 * có đường nào để người lạ tự đẩy chữ của mình lên đó mà không qua mắt người.
 */
enum FeedbackStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ duyệt',
            self::Published => 'Đang hiện trên trang',
            self::Rejected => 'Đã ẩn',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'is-warning',
            self::Published => 'is-success',
            self::Rejected => 'is-muted',
        };
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
