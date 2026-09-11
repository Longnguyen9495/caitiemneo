<?php

namespace App\Enums;

/**
 * What happened to an attendance row, for the audit trail.
 *
 * Self-service clock events are logged alongside manual edits so one timeline
 * answers "who touched this shift, when, and why".
 */
enum AttendanceAuditAction: string
{
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';
    case ManualCreate = 'manual_create';
    case ManualUpdate = 'manual_update';
    case ManualDelete = 'manual_delete';
    case OvertimeApproved = 'overtime_approved';
    case OvertimeRejected = 'overtime_rejected';

    public function label(): string
    {
        return match ($this) {
            self::CheckIn => 'Vào ca',
            self::CheckOut => 'Ra ca',
            self::ManualCreate => 'Quản lý thêm ca',
            self::ManualUpdate => 'Quản lý sửa ca',
            self::ManualDelete => 'Quản lý xóa ca',
            self::OvertimeApproved => 'Duyệt tăng ca',
            self::OvertimeRejected => 'Từ chối tăng ca',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::CheckIn, self::CheckOut, self::OvertimeApproved => 'is-success',
            self::ManualDelete, self::OvertimeRejected => 'is-danger',
            default => 'is-warning',
        };
    }
}
