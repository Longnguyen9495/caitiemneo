<?php

namespace App\Enums;

/**
 * The vocabulary of the company-wide audit trail.
 *
 * Kept deliberately coarse: the interesting detail lives in the before/after
 * snapshot, while this enum answers "what kind of thing happened" for filtering
 * and for the exception queue.
 */
enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Voided = 'voided';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Exported = 'exported';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Tạo mới',
            self::Updated => 'Chỉnh sửa',
            self::Deleted => 'Xóa',
            self::Paid => 'Thanh toán',
            self::Cancelled => 'Hủy',
            self::Voided => 'Hủy giao dịch',
            self::Approved => 'Duyệt',
            self::Rejected => 'Từ chối',
            self::Exported => 'Xuất dữ liệu',
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
