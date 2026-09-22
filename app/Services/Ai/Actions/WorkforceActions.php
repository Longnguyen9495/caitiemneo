<?php

namespace App\Services\Ai\Actions;

use App\Actions\Attendance\ReviewOvertimeAction;
use App\Actions\Attendance\SaveManualAttendanceAction;
use App\Actions\Attendance\ScheduleShiftAction;
use App\Enums\AttendanceStatus;
use App\Enums\OvertimeStatus;
use App\Http\Requests\Admin\AttendanceRecordRequest;
use App\Http\Requests\Admin\ReviewOvertimeRequest;
use App\Http\Requests\Admin\ShiftAssignmentRequest;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Thao tác trên chấm công và phân ca.
 *
 * Chấm công tay là chỗ dễ bị sửa lén nhất trong cả hệ thống, nên mọi phiếu ở
 * đây đều bắt buộc có lý do — đúng như form quản trị — và lý do đó đi thẳng
 * vào audit log.
 */
final class WorkforceActions
{
    /** @return array<int, ActionDefinition> */
    public static function all(): array
    {
        return [
            self::createAttendance(),
            self::updateAttendance(),
            self::deleteAttendance(),
            self::reviewOvertime(),
            self::assignShift(),
            self::removeShiftAssignment(),
        ];
    }

    private static function createAttendance(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'create_attendance',
            label: 'Ghi công tay',
            operation: 'create',
            resource: 'attendance',
            destructive: false,
            fields: fn (?int $branchId): array => self::attendanceFields(),
            validator: Validation::formRequest(AttendanceRecordRequest::class),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                $reason = (string) $payload['reason'];
                unset($payload['reason']);

                return app(SaveManualAttendanceAction::class)->create($payload, $actor, $reason);
            },
        );
    }

    private static function updateAttendance(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'update_attendance',
            label: 'Sửa công',
            operation: 'update',
            resource: 'attendance',
            destructive: false,
            fields: fn (?int $branchId): array => array_merge(
                [self::attendancePicker('Ca công cần sửa')],
                self::attendanceFields(),
            ),
            validator: Validation::formRequest(AttendanceRecordRequest::class, 'attendance'),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                $reason = (string) $payload['reason'];
                unset($payload['reason'], $payload['attendance_id']);

                return app(SaveManualAttendanceAction::class)->update($subject, $payload, $actor, $reason);
            },
            subject: self::attendanceSubject(),
        );
    }

    private static function deleteAttendance(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'delete_attendance',
            label: 'Xóa ca công',
            operation: 'delete',
            resource: 'attendance',
            destructive: true,
            fields: fn (?int $branchId): array => [
                self::attendancePicker('Ca công cần xóa'),
                Field::textarea('reason', 'Lý do xóa')->required()->help('Ghi trùng, ghi nhầm người, nhầm ngày…'),
            ],
            validator: Validation::inline([
                'attendance_id' => ['required', 'integer'],
                'reason' => ['required', 'string', 'max:255'],
            ], ['attendance_id' => 'ca công', 'reason' => 'lý do xóa']),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                Gate::forUser($actor)->authorize('delete', $subject);
                app(SaveManualAttendanceAction::class)->delete($subject, $actor, $payload['reason']);

                return $subject;
            },
            subject: self::attendanceSubject(),
            branchScoped: false,
            hint: 'Xóa hẳn dòng công; chỉ còn dấu vết trong lịch sử thao tác.',
        );
    }

    private static function reviewOvertime(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'review_overtime',
            label: 'Duyệt tăng ca',
            operation: 'update',
            resource: 'attendance',
            destructive: false,
            fields: fn (?int $branchId): array => [
                self::attendancePicker('Ca công có tăng ca'),
                Field::select('overtime_status', 'Kết luận', OvertimeStatus::decisionOptions())->required(),
                Field::number('approved_overtime_minutes', 'Số phút duyệt')->attributes(['min' => 0, 'max' => 1440])
                    ->help('Bỏ trống thì duyệt đúng số phút hệ thống ghi nhận.'),
                Field::textarea('overtime_approval_note', 'Ghi chú'),
            ],
            validator: Validation::formRequest(ReviewOvertimeRequest::class, 'attendance'),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                $minutes = $payload['approved_overtime_minutes'] ?? null;

                return app(ReviewOvertimeAction::class)->handle(
                    $subject,
                    $actor,
                    OvertimeStatus::from($payload['overtime_status']),
                    $minutes === null ? null : (int) $minutes,
                    $payload['overtime_approval_note'] ?? null,
                );
            },
            subject: self::attendanceSubject(),
            branchScoped: false,
        );
    }

    private static function assignShift(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'assign_shift',
            label: 'Phân ca',
            operation: 'create',
            resource: 'shift_assignment',
            destructive: false,
            fields: fn (?int $branchId): array => [
                Field::select('employee_id', 'Nhân viên', fn (?int $id): array => Catalogue::employees($id))->required(),
                Field::select('work_shift_id', 'Ca làm', fn (?int $id): array => Catalogue::workShifts())->required(),
                Field::date('work_date', 'Ngày làm')->required(),
                Field::textarea('note', 'Ghi chú'),
            ],
            validator: Validation::formRequest(ShiftAssignmentRequest::class),
            handler: fn (array $payload, User $actor, ?Model $subject): Model => app(ScheduleShiftAction::class)->handle(
                Branch::query()->findOrFail($payload['branch_id']),
                WorkShift::query()->findOrFail($payload['work_shift_id']),
                User::query()->findOrFail($payload['employee_id']),
                Carbon::parse($payload['work_date'])->toDateString(),
                $actor,
                $payload['note'] ?? null,
            ),
        );
    }

    private static function removeShiftAssignment(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'remove_shift_assignment',
            label: 'Bỏ phân ca',
            operation: 'delete',
            resource: 'shift_assignment',
            destructive: true,
            fields: fn (?int $branchId): array => [
                Field::select('shift_assignment_id', 'Ca đã phân', fn (?int $id): array => Catalogue::shiftAssignments($id))->required(),
                Field::textarea('reason', 'Lý do bỏ phân ca')->required()->help('Đổi lịch, nhân viên nghỉ, phân nhầm người…'),
            ],
            validator: Validation::inline([
                'shift_assignment_id' => ['required', 'integer'],
                'reason' => ['required', 'string', 'max:255'],
            ], ['shift_assignment_id' => 'ca đã phân', 'reason' => 'lý do bỏ phân ca']),
            // Xóa cứng nhưng bản chụp được ghi trước, ngay trong action, nên
            // dòng bị gỡ vẫn tra ngược được từ lịch sử thao tác.
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                Gate::forUser($actor)->authorize('delete', $subject);

                return app(ScheduleShiftAction::class)->remove($subject, $actor, $payload['reason']);
            },
            subject: Subject::inBranch(ShiftAssignment::class, 'shift_assignment_id', 'ca đã phân'),
            branchScoped: false,
        );
    }

    private static function attendancePicker(string $label): Field
    {
        return Field::select('attendance_id', $label, fn (?int $id): array => Catalogue::attendanceRecords($id))->required();
    }

    private static function attendanceSubject(): Closure
    {
        return Subject::inBranch(AttendanceRecord::class, 'attendance_id', 'ca công');
    }

    /** @return array<int, Field> */
    private static function attendanceFields(): array
    {
        return [
            Field::select('employee_id', 'Nhân viên', fn (?int $id): array => Catalogue::employees($id))->required(),
            Field::date('work_date', 'Ngày công')->required(),
            Field::text('shift_name', 'Tên ca')->required()->help('Ca sáng, ca chiều…'),
            Field::number('shift_value', 'Hệ số công')->default(1)->required()->attributes(['step' => 'any', 'min' => 0, 'max' => 10]),
            Field::select('status', 'Trạng thái', AttendanceStatus::options())->required(),
            Field::datetime('checked_in_at', 'Giờ vào'),
            Field::datetime('checked_out_at', 'Giờ ra'),
            Field::textarea('note', 'Ghi chú'),
            Field::textarea('reason', 'Lý do ghi tay')->required()
                ->help('Bắt buộc: câu này đi vào lịch sử thao tác để người sau hiểu vì sao.'),
        ];
    }
}
