<?php

namespace App\Enums;

/**
 * Approval state of the minutes worked past the planned end of a shift.
 *
 * Overtime that nobody approved must never reach payroll, so a freshly
 * detected overrun starts at `Pending` and stays there until an owner or a
 * manager of that branch acts on it.
 */
enum OvertimeStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Không tăng ca',
            self::Pending => 'Chờ duyệt',
            self::Approved => 'Đã duyệt',
            self::Rejected => 'Từ chối',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::None => 'is-muted',
            self::Pending => 'is-warning',
            self::Approved => 'is-success',
            self::Rejected => 'is-danger',
        };
    }

    /** Only an approved overrun may ever be used by payroll. */
    public function isPayable(): bool
    {
        return $this === self::Approved;
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

    /** The decisions a reviewer may record. */
    public static function decisionOptions(): array
    {
        return [
            self::Approved->value => self::Approved->label(),
            self::Rejected->value => self::Rejected->label(),
        ];
    }
}
