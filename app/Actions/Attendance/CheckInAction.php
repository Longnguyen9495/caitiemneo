<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceAuditAction;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\GpsVerification;
use App\Enums\OvertimeStatus;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Attendance\AttendanceAuditor;
use App\Services\Attendance\GpsVerifier;
use App\Support\Coordinates;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An employee starting their rostered shift from their own phone.
 *
 * Nothing that can be derived is taken from the request: the employee comes
 * from the session, the shift and branch come from the roster, and the time
 * comes from the server clock. Only the coordinates and the accuracy the
 * browser reports are supplied by the client, and both are re-checked here.
 */
class CheckInAction
{
    public function __construct(
        private GpsVerifier $gpsVerifier,
        private AttendanceAuditor $auditor,
    ) {}

    /**
     * @param  array{ip: string|null, user_agent: string|null}  $client
     *
     * @throws ValidationException
     */
    public function handle(
        User $employee,
        ShiftAssignment $assignment,
        Coordinates $point,
        int $accuracyMeters,
        array $client,
        ?CarbonInterface $now = null,
    ): AttendanceRecord {
        $this->assertOwnShift($employee, $assignment);

        $moment = $now ?? now();

        $this->assertWithinClockInWindow($assignment, $moment);

        $branch = Branch::query()->findOrFail($assignment->branch_id);
        $this->assertStillPostedToBranch($employee, $branch, $assignment);

        $distance = $this->gpsVerifier->verify($branch, $point, $accuracyMeters);

        return DB::transaction(function () use ($employee, $assignment, $branch, $point, $accuracyMeters, $distance, $client, $moment): AttendanceRecord {
            // Locking the roster row serialises two taps racing each other; the
            // unique key on (employee, date, shift name) is the backstop if the
            // lock is ever lost.
            ShiftAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());

            $this->assertNotAlreadyRecorded($employee, $assignment);

            [$status, $lateMinutes] = $this->evaluateLateness($assignment, $moment);

            try {
                $record = AttendanceRecord::query()->create([
                    'branch_id' => $branch->getKey(),
                    'shift_assignment_id' => $assignment->getKey(),
                    'employee_id' => $employee->getKey(),
                    'work_date' => $assignment->work_date->toDateString(),
                    'shift_name' => $assignment->shift_name,
                    'shift_value' => $assignment->shift_value,
                    'status' => $status,
                    'checked_in_at' => $moment,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'gps' => 'Ca này đã được chấm công rồi.',
                ]);
            }

            $record->forceFill([
                'source' => AttendanceSource::Gps,
                'check_in_latitude' => $point->latitude,
                'check_in_longitude' => $point->longitude,
                'check_in_accuracy_meters' => $accuracyMeters,
                'check_in_distance_meters' => $distance,
                'check_in_ip' => $client['ip'] ?? null,
                'check_in_user_agent' => $this->trimUserAgent($client['user_agent'] ?? null),
                'check_in_verification' => GpsVerification::Verified,
                'late_minutes' => $lateMinutes,
                'overtime_status' => OvertimeStatus::None,
            ])->save();

            $this->auditor->record(
                $record,
                $employee,
                AttendanceAuditAction::CheckIn,
                null,
                $record->auditSnapshot(),
                sprintf('Vào ca bằng GPS, cách cửa hàng %d mét.', $distance),
            );

            return $record;
        });
    }

    /** Nobody clocks in for anybody else, whatever the request says. */
    private function assertOwnShift(User $employee, ShiftAssignment $assignment): void
    {
        if ((int) $assignment->employee_id !== (int) $employee->getKey()) {
            throw ValidationException::withMessages([
                'gps' => 'Ca này không thuộc về tài khoản của bạn.',
            ]);
        }
    }

    /**
     * A posting that has ended must not keep letting somebody clock in.
     *
     * The question is asked about the business date of the shift, not today,
     * because a shift can be worked before the roster is looked at again.
     */
    private function assertStillPostedToBranch(User $employee, Branch $branch, ShiftAssignment $assignment): void
    {
        if ($employee->canAccessBranch($branch, $assignment->work_date->toDateString())) {
            return;
        }

        throw ValidationException::withMessages([
            'gps' => 'Bạn không còn được phân công về chi nhánh này vào ngày làm của ca.',
        ]);
    }

    private function assertWithinClockInWindow(ShiftAssignment $assignment, CarbonInterface $moment): void
    {
        $earliest = $assignment->earliestCheckInAt();

        if ($moment->lt($earliest)) {
            throw ValidationException::withMessages([
                'gps' => sprintf('Chưa tới giờ vào ca. Bạn có thể chấm công từ %s.', $earliest->format('H:i')),
            ]);
        }

        if ($moment->gt($assignment->latestCheckInAt())) {
            throw ValidationException::withMessages([
                'gps' => 'Ca này đã kết thúc. Hãy nhờ quản lý bổ sung chấm công kèm lý do.',
            ]);
        }
    }

    /** One shift, one attendance row, no matter which way it was created. */
    private function assertNotAlreadyRecorded(User $employee, ShiftAssignment $assignment): void
    {
        $existing = AttendanceRecord::query()
            ->where('employee_id', $employee->getKey())
            ->where(fn ($query) => $query
                ->where('shift_assignment_id', $assignment->getKey())
                ->orWhere(fn ($inner) => $inner
                    ->whereDate('work_date', $assignment->work_date->toDateString())
                    ->where('shift_name', $assignment->shift_name)))
            ->lockForUpdate()
            ->first();

        if ($existing === null) {
            return;
        }

        throw ValidationException::withMessages([
            'gps' => $existing->checked_in_at !== null
                ? sprintf('Bạn đã vào ca lúc %s rồi.', $existing->checked_in_at->format('H:i'))
                : sprintf('Ca này đã được quản lý ghi nhận là %s.', $existing->status->label()),
        ]);
    }

    /**
     * Present or late, and by how many minutes.
     *
     * Grace decides whether the arrival counts as late at all; the minutes
     * reported are measured from the planned start, so a five minute grace and
     * a seven minute arrival is "late by 7", not "late by 2".
     *
     * @return array{0: AttendanceStatus, 1: int}
     */
    private function evaluateLateness(ShiftAssignment $assignment, CarbonInterface $moment): array
    {
        $plannedStart = $assignment->planned_start_at;

        if ($moment->lte($plannedStart)) {
            return [AttendanceStatus::Present, 0];
        }

        $minutesLate = (int) floor($plannedStart->diffInSeconds($moment) / 60);

        return $minutesLate > (int) $assignment->grace_minutes
            ? [AttendanceStatus::Late, $minutesLate]
            : [AttendanceStatus::Present, 0];
    }

    /** The column holds 255 characters; a long agent string is simply cut. */
    private function trimUserAgent(?string $userAgent): ?string
    {
        return $userAgent === null ? null : mb_substr($userAgent, 0, 255);
    }
}
