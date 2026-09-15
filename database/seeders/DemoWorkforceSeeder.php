<?php

namespace Database\Seeders;

use App\Actions\Attendance\ScheduleShiftAction;
use App\Actions\Shifts\ManageShiftRequestAction;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\GpsVerification;
use App\Enums\OvertimeStatus;
use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\EmployeeBranchAssignment;
use App\Models\EmployeeCompensationProfile;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequest;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Nhân sự, lịch ca, chấm công và các đơn xin nghỉ / đổi ca.
 *
 * Khác với lệnh `attendance:demo` — thứ xoá sạch khoảng thời gian rồi dựng lại
 * — seeder này chỉ bồi vào chỗ còn trống. Đó là điều kiện để chạy được trên
 * một cơ sở dữ liệu đã có lịch ca do người thật xếp: ca nào đã có thì giữ
 * nguyên, và không ca nào đang bị một đơn nghỉ khoá lại bị xoá mất.
 *
 * Chấm công ghi thẳng chứ không qua hai action check-in/check-out, vì hai
 * action đó luôn lấy mốc "bây giờ" nên không dựng lại được quá khứ.
 */
class DemoWorkforceSeeder extends Seeder
{
    public function __construct(
        private ScheduleShiftAction $scheduleShift,
        private ManageShiftRequestAction $shiftRequests,
    ) {}

    public function run(): void
    {
        $this->extraStaff();

        $staff = DemoData::staff();
        $shifts = WorkShift::query()->where('is_active', true)->orderBy('id')->get();

        if ($staff->isEmpty() || $shifts->isEmpty()) {
            $this->command?->warn('  Chưa có nhân sự hoặc ca mẫu, bỏ qua lịch ca demo.');

            return;
        }

        $this->roster($staff, $shifts);
        $this->attendance();
        $this->requests($staff);
    }

    /**
     * Thêm thợ để danh sách nhân sự đủ đông cho một tiệm hai cơ sở.
     *
     * {@see StaffSeeder} đã dựng khung quyền (chủ, hai quản lý, thợ cố định,
     * một người luân chuyển); ở đây chỉ bổ sung thợ thường.
     */
    private function extraStaff(): void
    {
        $rows = [
            ['email' => 'trang.th@caitiemneo.test', 'name' => 'Đỗ Quỳnh Trang', 'branch' => 'CN-01', 'base_salary' => 5_000_000, 'shift_rate' => 200_000, 'commission_rate' => 15],
            ['email' => 'ngoc.th@caitiemneo.test', 'name' => 'Vũ Bảo Ngọc', 'branch' => 'CN-01', 'base_salary' => 4_500_000, 'shift_rate' => 190_000, 'commission_rate' => 12],
            ['email' => 'anh.ndc@caitiemneo.test', 'name' => 'Lê Phương Anh', 'branch' => 'CN-02', 'base_salary' => 5_000_000, 'shift_rate' => 210_000, 'commission_rate' => 15],
            ['email' => 'yen.ndc@caitiemneo.test', 'name' => 'Hoàng Hải Yến', 'branch' => 'CN-02', 'base_salary' => 4_500_000, 'shift_rate' => 190_000, 'commission_rate' => 12],
        ];

        foreach ($rows as $row) {
            $branch = Branch::query()->where('code', $row['branch'])->first();

            if ($branch === null) {
                continue;
            }

            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'role' => UserRole::Employee,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'phone' => fake()->numerify('09########'),
                    'is_active' => true,
                    'base_salary' => $row['base_salary'],
                    'shift_rate' => $row['shift_rate'],
                    'commission_rate' => $row['commission_rate'],
                    'can_manage_appointments' => true,
                    'can_create_invoices' => true,
                    'can_manage_payroll' => false,
                ],
            );

            EmployeeCompensationProfile::query()->updateOrCreate(
                ['user_id' => $user->getKey(), 'branch_id' => null, 'effective_from' => DemoData::start()->toDateString()],
                [
                    'base_salary' => $row['base_salary'],
                    'shift_rate' => $row['shift_rate'],
                    'regular_commission_rate' => $row['commission_rate'],
                    'overtime_commission_rate' => min(100, $row['commission_rate'] + 5),
                    'effective_to' => null,
                ],
            );

            EmployeeBranchAssignment::query()->updateOrCreate(
                ['user_id' => $user->getKey(), 'branch_id' => $branch->getKey(), 'starts_on' => DemoData::start()->toDateString()],
                ['is_primary' => true, 'ends_on' => null],
            );
        }
    }

    /**
     * Xếp mỗi người một ca mỗi ngày mở cửa, tại chi nhánh họ thuộc về hôm đó.
     *
     * Đi qua {@see ScheduleShiftAction} nên mỗi dòng mang theo bản chụp giờ kế
     * hoạch, ân hạn và giá trị ca đúng như khi quản lý tự xếp trên giao diện.
     *
     * @param  Collection<int, User>  $staff
     * @param  Collection<int, WorkShift>  $shifts
     */
    private function roster(Collection $staff, Collection $shifts): void
    {
        $actor = DemoData::actor();
        $branches = DemoData::branches()->keyBy('id');
        $created = 0;

        foreach ($staff->values() as $staffIndex => $employee) {
            for ($date = DemoData::start()->copy(); $date->lte(DemoData::end()); $date->addDay()) {
                // Ngày nghỉ của mỗi người lệch nhau một thứ, để quầy không bao
                // giờ trống người mà ai cũng có ngày nghỉ riêng.
                if (! DemoData::isOpen($date) || $date->dayOfWeek === ($staffIndex % 6) + 1) {
                    continue;
                }

                $branch = $branches->get(DemoData::branchIdFor($employee, $date));

                if ($branch === null) {
                    continue;
                }

                $alreadyRostered = ShiftAssignment::query()
                    ->where('employee_id', $employee->getKey())
                    ->whereDate('work_date', $date->toDateString())
                    ->exists();

                if ($alreadyRostered) {
                    continue;
                }

                $shift = $shifts->values()[($staffIndex + $date->dayOfYear) % $shifts->count()];

                try {
                    $this->scheduleShift->handle($branch, $shift, $employee, $date->toDateString(), $actor);
                    $created++;
                } catch (ValidationException) {
                    // Ca trùng giờ hoặc nhân viên không còn thuộc chi nhánh đó.
                }
            }
        }

        $this->command?->line(sprintf('  Đã xếp %d ca làm.', $created));
    }

    /**
     * Dựng chấm công cho mọi ca đã qua mà chưa có dòng nào.
     *
     * Tỷ lệ đi muộn / nghỉ / vắng rải theo chỉ số dòng thay vì random thuần,
     * để lần chạy nào cũng ra cùng một bức tranh và các màn hình duyệt công,
     * thưởng chuyên cần đều có việc để hiển thị.
     */
    private function attendance(): void
    {
        $assignments = ShiftAssignment::query()
            ->with('branch')
            ->whereDate('work_date', '>=', DemoData::start()->toDateString())
            ->whereDate('work_date', '<', DemoData::today()->toDateString())
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();

        $created = 0;

        foreach ($assignments->values() as $index => $assignment) {
            $alreadyRecorded = AttendanceRecord::query()
                ->where('employee_id', $assignment->employee_id)
                ->whereDate('work_date', $assignment->work_date->toDateString())
                ->where('shift_name', $assignment->shift_name)
                ->exists();

            if ($alreadyRecorded) {
                continue;
            }

            $this->recordAttendance($assignment, $index);
            $created++;
        }

        $this->command?->line(sprintf('  Đã ghi %d dòng chấm công.', $created));
    }

    private function recordAttendance(ShiftAssignment $assignment, int $index): void
    {
        $status = match (true) {
            $index % 23 === 0 => AttendanceStatus::Absent,
            $index % 17 === 0 => AttendanceStatus::Leave,
            $index % 9 === 0 => AttendanceStatus::Late,
            default => AttendanceStatus::Present,
        };

        $record = new AttendanceRecord;
        $record->forceFill([
            'branch_id' => $assignment->branch_id,
            'shift_assignment_id' => $assignment->getKey(),
            'employee_id' => $assignment->employee_id,
            'work_date' => $assignment->work_date->toDateString(),
            'shift_name' => $assignment->shift_name,
            // Ngày nghỉ và ngày vắng không tính giá trị ca.
            'shift_value' => in_array($status, [AttendanceStatus::Leave, AttendanceStatus::Absent], true) ? 0 : $assignment->shift_value,
            'status' => $status,
            'is_self_recorded' => false,
            'note' => match ($status) {
                AttendanceStatus::Leave => 'Nghỉ có báo trước',
                AttendanceStatus::Absent => 'Không tới, không báo',
                default => null,
            },
        ]);

        if ($status === AttendanceStatus::Leave || $status === AttendanceStatus::Absent) {
            $record->forceFill([
                'source' => AttendanceSource::Manual,
                'overtime_status' => OvertimeStatus::None,
            ])->save();

            return;
        }

        $this->stampClock($record, $assignment, $status, $index);
    }

    /** Điền giờ vào, giờ ra và bằng chứng GPS cho một ca có đi làm. */
    private function stampClock(AttendanceRecord $record, ShiftAssignment $assignment, AttendanceStatus $status, int $index): void
    {
        $grace = (int) $assignment->grace_minutes;
        $lateMinutes = $status === AttendanceStatus::Late ? fake()->numberBetween(6, 34) : 0;
        $overtimeMinutes = $index % 11 === 0 ? fake()->numberBetween(35, 95) : 0;

        $checkedInAt = $assignment->planned_start_at->copy()
            ->addMinutes($lateMinutes > 0 ? $grace + $lateMinutes : -fake()->numberBetween(1, 12));
        $checkedOutAt = $assignment->planned_end_at->copy()
            ->addMinutes($overtimeMinutes > 0 ? $overtimeMinutes : -fake()->numberBetween(0, 6));

        $branch = $assignment->branch;
        $hasCoordinates = $branch?->latitude !== null && $branch?->longitude !== null;

        $record->forceFill([
            'checked_in_at' => $checkedInAt,
            'checked_out_at' => $checkedOutAt,
            'late_minutes' => $lateMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'source' => $hasCoordinates ? AttendanceSource::Gps : AttendanceSource::Manual,
            'check_in_ip' => '192.168.1.'.(20 + $index % 60),
            'check_in_user_agent' => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/127 Mobile Safari/537.36',
        ] + $this->overtimeReview($overtimeMinutes, $assignment) + $this->gpsEvidence($branch, $hasCoordinates))->save();
    }

    /**
     * Giờ ngoài ca: ca cũ đã được duyệt, ca gần đây còn nằm chờ.
     *
     * Hai trạng thái cùng tồn tại thì màn hình duyệt tăng ca mới có cả hàng
     * đợi lẫn lịch sử để xem.
     *
     * @return array<string, mixed>
     */
    private function overtimeReview(int $overtimeMinutes, ShiftAssignment $assignment): array
    {
        if ($overtimeMinutes === 0) {
            return ['overtime_status' => OvertimeStatus::None, 'approved_overtime_minutes' => 0];
        }

        if ($assignment->work_date->gte(DemoData::today()->copy()->subDays(10))) {
            return ['overtime_status' => OvertimeStatus::Pending, 'approved_overtime_minutes' => 0];
        }

        return [
            'overtime_status' => OvertimeStatus::Approved,
            'approved_overtime_minutes' => $overtimeMinutes,
            'overtime_approved_by' => DemoData::actor()->getKey(),
            'overtime_approved_at' => $assignment->planned_end_at->copy()->addDay(),
            'overtime_approval_note' => 'Làm nốt khách nên quá ca.',
        ];
    }

    /** @return array<string, mixed> */
    private function gpsEvidence(?Branch $branch, bool $hasCoordinates): array
    {
        if (! $hasCoordinates) {
            return [
                'check_in_verification' => GpsVerification::Manual,
                'check_out_verification' => GpsVerification::Manual,
            ];
        }

        $jitter = fn (): float => fake()->numberBetween(-4, 4) / 10_000;

        return [
            'check_in_latitude' => (float) $branch->latitude + $jitter(),
            'check_in_longitude' => (float) $branch->longitude + $jitter(),
            'check_in_accuracy_meters' => fake()->numberBetween(6, 22),
            'check_in_distance_meters' => fake()->numberBetween(3, 38),
            'check_in_verification' => GpsVerification::Verified,
            'check_out_latitude' => (float) $branch->latitude + $jitter(),
            'check_out_longitude' => (float) $branch->longitude + $jitter(),
            'check_out_accuracy_meters' => fake()->numberBetween(6, 22),
            'check_out_distance_meters' => fake()->numberBetween(3, 38),
            'check_out_verification' => GpsVerification::Verified,
        ];
    }

    /**
     * Ba đơn nghỉ và một đơn đổi ca, mỗi đơn dừng ở một trạng thái khác nhau.
     *
     * Chỉ dùng ca tương lai, vì ca đã chấm công thì action từ chối thay đổi.
     *
     * @param  Collection<int, User>  $staff
     */
    private function requests(Collection $staff): void
    {
        if (ShiftRequest::query()->count() >= 5) {
            return;
        }

        $actor = DemoData::actor();
        $future = ShiftAssignment::query()
            ->whereDate('work_date', '>', DemoData::today()->copy()->addDay()->toDateString())
            ->orderBy('work_date')
            ->get()
            ->groupBy('employee_id');

        $this->leaveRequest($staff, $future, $actor, 'Con nhỏ ốm, xin nghỉ một ca', decide: null);
        $this->leaveRequest($staff, $future, $actor, 'Về quê giỗ họ', decide: true);
        $this->leaveRequest($staff, $future, $actor, 'Xin nghỉ đi chơi cuối tuần', decide: false);
        $this->swapRequest($future);
    }

    /**
     * @param  Collection<int, User>  $staff
     * @param  Collection<int, Collection<int, ShiftAssignment>>  $future
     */
    private function leaveRequest(Collection $staff, Collection $future, User $actor, string $reason, ?bool $decide): void
    {
        foreach ($staff as $employee) {
            $assignment = $future->get($employee->getKey())?->first(
                fn (ShiftAssignment $candidate): bool => ! ShiftRequest::query()
                    ->where('shift_assignment_id', $candidate->getKey())
                    ->exists(),
            );

            if ($assignment === null) {
                continue;
            }

            try {
                $request = $this->shiftRequests->createLeave($employee, $assignment->getKey(), $reason);

                if ($decide !== null) {
                    $this->shiftRequests->decide(
                        $actor,
                        $request,
                        $decide,
                        $decide ? 'Đã có người trực thay' : 'Cuối tuần cao điểm, không bố trí được người',
                    );
                }

                return;
            } catch (ValidationException) {
                continue;
            }
        }
    }

    /** @param Collection<int, Collection<int, ShiftAssignment>> $future */
    private function swapRequest(Collection $future): void
    {
        $sameDayGroups = $future->flatten()->groupBy(
            fn (ShiftAssignment $assignment): string => $assignment->work_date->toDateString().'#'.$assignment->branch_id,
        );

        foreach ($sameDayGroups as $sameDay) {
            if ($sameDay->count() < 2) {
                continue;
            }

            [$mine, $theirs] = [$sameDay->values()[0], $sameDay->values()[1]];
            $requester = User::query()->find($mine->employee_id);
            $recipient = User::query()->find($theirs->employee_id);

            if ($requester === null || $recipient === null) {
                continue;
            }

            try {
                $request = $this->shiftRequests->createSwap(
                    $requester,
                    $mine->getKey(),
                    $recipient->getKey(),
                    $theirs->getKey(),
                    'Đổi ca để kịp lịch học buổi tối',
                );

                // Người nhận đã đồng ý, đơn đang chờ quản lý chốt.
                $this->shiftRequests->respond($recipient, $request, true, 'Mình đổi được');

                return;
            } catch (ValidationException) {
                continue;
            }
        }
    }
}
