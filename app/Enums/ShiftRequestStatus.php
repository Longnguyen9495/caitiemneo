<?php

namespace App\Enums;

enum ShiftRequestStatus: string
{
    case PendingApproval = 'pending_approval';
    case PendingRecipient = 'pending_recipient';
    case RecipientConfirmed = 'recipient_confirmed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Chờ duyệt',
            self::PendingRecipient => 'Chờ người nhận xác nhận',
            self::RecipientConfirmed => 'Đã xác nhận, chờ duyệt',
            self::Approved => 'Đã duyệt',
            self::Rejected => 'Đã từ chối',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::PendingApproval, self::PendingRecipient, self::RecipientConfirmed], true);
    }

    public function canBeCancelledByRequester(): bool
    {
        return in_array($this, [self::PendingApproval, self::PendingRecipient], true);
    }
}
