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

    /**
     * Tông màu của chip trạng thái.
     *
     * Sáu trạng thái, sáu màu khác nhau. Hai đơn cùng đang chờ mà chung một màu
     * thì người duyệt phải đọc hết chữ mới biết đơn nào đến lượt mình xử lý,
     * và màu trở thành trang trí thay vì thông tin.
     *
     * Vàng là việc đang nằm ở bàn quản lý; tím nhạt là đang chờ người khác trả
     * lời, không phải việc của quản lý.
     */
    public function tone(): string
    {
        return match ($this) {
            self::PendingApproval => 'is-warning',
            self::PendingRecipient => 'is-info',
            self::RecipientConfirmed => 'is-active',
            self::Approved => 'is-success',
            self::Rejected => 'is-danger',
            self::Cancelled => 'is-muted',
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
