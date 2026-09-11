<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceAuditAction;
use App\Enums\GpsVerification;
use App\Enums\OvertimeStatus;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\User;
use App\Services\Attendance\AttendanceAuditor;
use App\Services\Attendance\GpsVerifier;
use App\Support\Coordinates;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An employee closing their own open shift.
 *
 * Time past the planned end is recorded, never assumed: the minutes land in
 * `overtime_minutes` with status `Pending` and are worth nothing until a
 * manager approves them.
 */
class CheckOutAction
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
        AttendanceRecord $record,
        Coordinates $point,
        int $accuracyMeters,
        array $client,
        ?CarbonInterface $now = null,
    ): AttendanceRecord {
        $this->assertOwnRecord($employee, $record);

        $branch = Branch::query()->findOrFail($record->branch_id);
        $distance = $this->gpsVerifier->verify($branch, $point, $accuracyMeters);
        $moment = $now ?? now();

        return DB::transaction(function () use ($employee, $record, $point, $accuracyMeters, $distance, $client, $moment): AttendanceRecord {
            $locked = AttendanceRecord::query()->lockForUpdate()->findOrFail($record->getKey());

            $this->assertOwnRecord($employee, $locked);
            $this->assertSessionIsOpen($locked);

            if ($moment->lte($locked->checked_in_at)) {
                throw ValidationException::withMessages([
                    'gps' => 'Giờ ra phải sau giờ vào. Hãy thử lại sau ít phút.',
                ]);
            }

            $before = $locked->auditSnapshot();
            $overtimeMinutes = $this->overtimeMinutes($locked, $moment);

            $locked->forceFill([
                'checked_out_at' => $moment,
                'check_out_latitude' => $point->latitude,
                'check_out_longitude' => $point->longitude,
                'check_out_accuracy_meters' => $accuracyMeters,
                'check_out_distance_meters' => $distance,
                'check_out_ip' => $client['ip'] ?? null,
                'check_out_user_agent' => $client['user_agent'] === null ? null : mb_substr($client['user_agent'], 0, 255),
                'check_out_verification' => GpsVerification::Verified,
                'overtime_minutes' => $overtimeMinutes,
                'approved_overtime_minutes' => 0,
                'overtime_status' => $overtimeMinutes > 0 ? OvertimeStatus::Pending : OvertimeStatus::None,
            ])->save();

            $this->auditor->record(
                $locked,
                $employee,
                AttendanceAuditAction::CheckOut,
                $before,
                $locked->auditSnapshot(),
                $overtimeMinutes > 0
                    ? sprintf('Ra ca bằng GPS, vượt ca %d phút và đang chờ duyệt.', $overtimeMinutes)
                    : sprintf('Ra ca bằng GPS, cách cửa hàng %d mét.', $distance),
            );

            return $locked;
        });
    }

    private function assertOwnRecord(User $employee, AttendanceRecord $record): void
    {
        if ((int) $record->employee_id !== (int) $employee->getKey()) {
            throw ValidationException::withMessages([
                'gps' => 'Ca này không thuộc về tài khoản của bạn.',
            ]);
        }
    }

    private function assertSessionIsOpen(AttendanceRecord $record): void
    {
        if ($record->checked_in_at === null) {
            throw ValidationException::withMessages([
                'gps' => 'Bạn chưa vào ca nên chưa thể ra ca.',
            ]);
        }

        if ($record->checked_out_at !== null) {
            throw ValidationException::withMessages([
                'gps' => sprintf('Bạn đã ra ca lúc %s rồi.', $record->checked_out_at->format('H:i')),
            ]);
        }
    }

    /**
     * Minutes worked past the planned end of the shift.
     *
     * A row with no roster behind it — a shift a manager typed in by hand —
     * has no planned end to measure against, so it produces no overtime.
     */
    private function overtimeMinutes(AttendanceRecord $record, CarbonInterface $moment): int
    {
        $plannedEnd = $record->shiftAssignment?->planned_end_at;

        if ($plannedEnd === null || $moment->lte($plannedEnd)) {
            return 0;
        }

        return (int) floor($plannedEnd->diffInSeconds($moment) / 60);
    }
}
