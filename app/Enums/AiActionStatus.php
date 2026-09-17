<?php

namespace App\Enums;

enum AiActionStatus: string
{
    case Pending = 'pending';
    case Executing = 'executing';
    case Executed = 'executed';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Executing => 'Đang thực hiện',
            self::Executed => 'Đã thực hiện',
            self::Rejected => 'Đã từ chối',
            self::Failed => 'Thực hiện thất bại',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Executed, self::Rejected, self::Failed], true);
    }
}
