<?php

namespace App\Enums;

enum LeaveEntitlement: string
{
    case Paid = 'paid';
    case Unpaid = 'unpaid';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Nghỉ hưởng lương',
            self::Unpaid => 'Nghỉ không hưởng lương',
        };
    }
}
