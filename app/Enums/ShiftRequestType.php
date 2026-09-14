<?php

namespace App\Enums;

enum ShiftRequestType: string
{
    case Leave = 'leave';
    case Swap = 'swap';

    public function label(): string
    {
        return match ($this) {
            self::Leave => 'Đơn xin nghỉ',
            self::Swap => 'Đơn đổi ca',
        };
    }
}
