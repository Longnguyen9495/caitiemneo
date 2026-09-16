<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\OvertimeStatus;

/**
 * One entry inside a calendar day cell: a worked shift, planned shift, or absence.
 *
 * Immutable. Does not carry permissions; the service decides whether to include
 * IDs and URLs based on the actor before creating this value.
 */
final readonly class CalendarItem
{
    public function __construct(
        public ?int $recordId = null,
        public ?string $employeeName = null,
        public ?string $shiftName = null,
        public ?string $plannedStartAt = null,
        public ?string $plannedEndAt = null,
        public ?string $checkedInAt = null,
        public ?string $checkedOutAt = null,
        public ?AttendanceStatus $status = null,
        public int $lateMinutes = 0,
        public int $overtimeMinutes = 0,
        public ?OvertimeStatus $overtimeStatus = null,
        public ?AttendanceSource $source = null,
        public bool $isMissingCheckOut = false,
        public bool $isLockedByPayroll = false,
        public bool $isSelfRecorded = false,
        public bool $gpsNeedsReview = false,
        public ?string $viewUrl = null,
        public ?string $editUrl = null,
        public ?string $note = null,
    ) {}

    /**
     * Priority tone for this item. Higher severity = lower numeric value.
     * Used so the day cell can show the most serious issue first.
     */
    public function tonePriority(): int
    {
        return match ($this->status) {
            AttendanceStatus::Absent => 1,
            AttendanceStatus::Late => $this->isMissingCheckOut ? 2 : 4,
            AttendanceStatus::Present => $this->isMissingCheckOut ? 2 : ($this->overtimeNeedsReview() ? 3 : 6),
            AttendanceStatus::Leave => 5,
            null => $this->shiftName !== null ? 7 : 8,
        };
    }

    public function overtimeNeedsReview(): bool
    {
        return $this->overtimeStatus === OvertimeStatus::Pending;
    }

    /**
     * A short accessible description for screen readers, independent of colour.
     */
    public function accessibleSummary(): string
    {
        $parts = [];

        if ($this->shiftName !== null) {
            $parts[] = $this->shiftName;
        }

        if ($this->status !== null) {
            $parts[] = $this->status->label();
        }

        if ($this->isMissingCheckOut) {
            $parts[] = 'thiếu giờ ra';
        }

        if ($this->lateMinutes > 0) {
            $parts[] = "đi muộn {$this->lateMinutes} phút";
        }

        if ($this->overtimeNeedsReview()) {
            $parts[] = "tăng ca chờ duyệt {$this->overtimeMinutes} phút";
        } elseif ($this->overtimeMinutes > 0 && $this->overtimeStatus === OvertimeStatus::Approved) {
            $parts[] = "tăng ca đã duyệt {$this->overtimeMinutes} phút";
        }

        if ($this->gpsNeedsReview) {
            $parts[] = 'GPS cần xem lại';
        }

        if ($this->isLockedByPayroll) {
            $parts[] = 'đã khóa bởi kỳ lương';
        }

        return implode(', ', $parts);
    }
}
