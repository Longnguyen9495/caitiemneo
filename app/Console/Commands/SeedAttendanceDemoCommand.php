<?php

namespace App\Console\Commands;

use App\Enums\AttendanceAuditAction;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\GpsVerification;
use App\Enums\OvertimeStatus;
use App\Models\AttendanceAuditLog;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Attendance\AttendanceAuditor;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dựng dữ liệu phân ca và chấm công mẫu để xem thử giao diện.
 *
 * Mô phỏng đúng cách tiệm đang vận hành: mỗi người một ca cố định, nghỉ thì
 * báo trước, không đi muộn, và thỉnh thoảng quá ca vì làm nốt khách.
 *
 * Dữ liệu sinh ra là tất định (gieo theo id nhân viên và ngày), nên chạy lại
 * cho ra đúng kết quả cũ và có thể xoá sạch bằng --clear.
 */
class SeedAttendanceDemoCommand extends Command
{
    protected $signature = 'attendance:demo
        {--weeks=3 : Số tuần quá khứ cần dựng}
        {--ahead=1 : Số tuần tương lai chỉ phân ca, chưa chấm công}
        {--clear : Xoá dữ liệu trong khoảng thời gian thay vì tạo mới}
        {--coordinates : Điền toạ độ tạm cho chi nhánh chưa có, để thử nút Vào ca}
        {--force : Bỏ qua xác nhận}';

    protected $description = 'Tạo dữ liệu phân ca và chấm công mẫu cho môi trường thử nghiệm';

    /** Ngày nghỉ cố định trong tuần, rải đều để không ai trùng ai. */
    private const WEEKDAY_OFF = [1, 2, 3, 4, 5];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Lệnh này không chạy trên môi trường production.');

            return self::FAILURE;
        }

        $staff = $this->staff();

        if ($staff->isEmpty()) {
            $this->error('Chưa có nhân viên nào được phân công chi nhánh. Hãy chạy seeder nhân sự trước.');

            return self::FAILURE;
        }

        $from = CarbonImmutable::now()->startOfWeek()->subWeeks((int) $this->option('weeks'));
        $to = CarbonImmutable::now()->startOfWeek()->addWeeks((int) $this->option('ahead'))->endOfWeek();

        $this->line(sprintf('Khoảng thời gian: %s đến %s', $from->format('d/m/Y'), $to->format('d/m/Y')));

        $existing = $this->existingRows($staff, $from, $to);

        if ($existing > 0 && ! $this->option('force')) {
            $this->warn(sprintf('Đang có %d bản ghi trong khoảng này, chúng sẽ bị xoá trước khi dựng lại.', $existing));

            if (! $this->confirm('Tiếp tục?', false)) {
                return self::FAILURE;
            }
        }

        $this->clear($staff, $from, $to);

        if ($this->option('clear')) {
            $this->info('Đã xoá dữ liệu phân ca và chấm công trong khoảng trên.');

            return self::SUCCESS;
        }

        if ($this->option('coordinates')) {
            $this->fillPlaceholderCoordinates();
        }

        $this->build($staff, $from, $to);

        return self::SUCCESS;
    }

    /** Nhân sự có ca: nhân viên và quản lý đang hoạt động, đã có chi nhánh. */
    private function staff(): Collection
    {
        return User::query()
            ->active()
            ->whereIn('role', ['employee', 'manager'])
            ->whereHas('branchAssignments')
            ->with('branchAssignments')
            ->orderBy('id')
            ->get();
    }

    private function existingRows(Collection $staff, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $ids = $staff->modelKeys();

        return ShiftAssignment::query()->whereIn('employee_id', $ids)->betweenDates($from, $to)->count()
            + AttendanceRecord::query()->whereIn('employee_id', $ids)->whereBetween('work_date', [$from, $to])->count();
    }

    private function clear(Collection $staff, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $ids = $staff->modelKeys();

        DB::transaction(function () use ($ids, $from, $to): void {
            // Nhật ký chấm công cố ý KHÔNG còn cascade theo bản ghi, để xoá một
            // ca không xoá mất bằng chứng ai đã xoá nó. Ở đây là lệnh dựng dữ
            // liệu thử nghiệm nên phải tự dọn phần nhật ký của chính nó, và chỉ
            // đúng phần đó: lọc theo nhân sự và khoảng ngày đã sinh ra.
            AttendanceAuditLog::query()
                ->whereIn('employee_id_snapshot', $ids)
                ->whereBetween('work_date_snapshot', [$from, $to])
                ->delete();

            AttendanceRecord::query()
                ->whereIn('employee_id', $ids)
                ->whereBetween('work_date', [$from, $to])
                ->delete();

            ShiftAssignment::query()
                ->whereIn('employee_id', $ids)
                ->betweenDates($from, $to)
                ->delete();
        });
    }

    private function build(Collection $staff, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $today = CarbonImmutable::today();
        $plans = $this->plans($staff);
        $counters = ['roster' => 0, 'present' => 0, 'late' => 0, 'leave' => 0, 'overtime' => 0, 'open' => 0];

        DB::transaction(function () use ($plans, $from, $to, $today, &$counters): void {
            foreach ($plans as $plan) {
                for ($date = $from; $date->lte($to); $date = $date->addDay()) {
                    if ($date->dayOfWeekIso === $plan['day_off']) {
                        continue;
                    }

                    $assignment = $this->roster($plan, $date);
                    $counters['roster']++;

                    // Hôm nay để trống để còn bấm thử Vào ca trên điện thoại.
                    if ($date->gte($today)) {
                        continue;
                    }

                    $this->attend($plan, $assignment, $date, $counters);
                }
            }
        });

        $this->table(
            ['Ca đã phân', 'Đi làm', 'Đi trễ', 'Nghỉ phép', 'Có tăng ca', 'Thiếu giờ ra'],
            [[$counters['roster'], $counters['present'], $counters['late'], $counters['leave'], $counters['overtime'], $counters['open']]],
        );

        $this->info('Xong. Mở /admin/shift-schedule để xem lịch tuần, /admin/attendance/review để xem hàng chờ duyệt.');
    }

    /**
     * Ca cố định của từng người.
     *
     * "Mỗi đứa 1 ca" và ca không đổi giữa chừng, nên khung ca được chốt một
     * lần theo id thay vì bốc ngẫu nhiên mỗi ngày.
     *
     * @return array<int, array<string, mixed>>
     */
    private function plans(Collection $staff): array
    {
        $plans = [];
        $index = 0;

        foreach ($staff as $person) {
            $branchId = $person->primaryBranchId() ?? $person->branchAssignments->first()?->branch_id;

            if ($branchId === null) {
                continue;
            }

            $shifts = $this->shiftsFor($branchId);

            if ($shifts->isEmpty()) {
                continue;
            }

            // Quản lý nhận ca dài, nhân viên chia đều ba khung thường.
            $longShift = $shifts->first(fn (WorkShift $shift): bool => (float) $shift->shift_value > 1);
            $regular = $shifts->reject(fn (WorkShift $shift): bool => (float) $shift->shift_value > 1)->values();

            if ($regular->isEmpty()) {
                $regular = $shifts->values();
            }

            $shift = $person->isManager()
                ? ($longShift ?? $regular->first())
                : $regular[$index % $regular->count()];

            $plans[] = [
                'user' => $person,
                'branch_id' => (int) $branchId,
                'shift' => $shift,
                'day_off' => self::WEEKDAY_OFF[$index % count(self::WEEKDAY_OFF)],
            ];

            $index++;
        }

        return $plans;
    }

    /** @return \Illuminate\Support\Collection<int, WorkShift> */
    private function shiftsFor(int $branchId)
    {
        return WorkShift::query()
            ->active()
            ->usableAt([$branchId])
            ->orderBy('starts_at')
            ->get();
    }

    /** @param  array<string, mixed>  $plan */
    private function roster(array $plan, CarbonImmutable $date): ShiftAssignment
    {
        $shift = $plan['shift'];

        return ShiftAssignment::query()->create([
            'branch_id' => $plan['branch_id'],
            'employee_id' => $plan['user']->getKey(),
            'work_shift_id' => $shift->getKey(),
            'work_date' => $date->toDateString(),
            'shift_name' => $shift->name,
            'planned_start_at' => $shift->plannedStartOn($date),
            'planned_end_at' => $shift->plannedEndOn($date),
            'shift_value' => $shift->shift_value,
            'grace_minutes' => $shift->grace_minutes,
            'early_check_in_minutes' => $shift->early_check_in_minutes,
        ]);
    }

    /**
     * Một ngày làm việc đã xong.
     *
     * @param  array<string, mixed>  $plan
     * @param  array<string, int>  $counters
     */
    private function attend(array $plan, ShiftAssignment $assignment, CarbonImmutable $date, array &$counters): void
    {
        $roll = $this->roll($plan['user']->getKey(), $date);

        if ($roll % 17 === 0) {
            $this->leaveDay($plan, $assignment, $date, $counters);

            return;
        }

        // Không được phép đi muộn, nên gần như ai cũng tới sớm vài phút.
        $late = $roll % 23 === 0;
        $checkedIn = $late
            ? $assignment->planned_start_at->copy()->addMinutes(6 + ($roll % 12))
            : $assignment->planned_start_at->copy()->subMinutes(3 + ($roll % 9));

        $overtimeMinutes = $roll % 9 === 0 ? 20 + (($roll * 7) % 56) : 0;
        $forgotCheckOut = $roll % 29 === 0;

        $checkedOut = $forgotCheckOut
            ? null
            : $assignment->planned_end_at->copy()->addMinutes($overtimeMinutes > 0 ? $overtimeMinutes : -(2 + ($roll % 6)));

        if ($forgotCheckOut) {
            $overtimeMinutes = 0;
        }

        $record = AttendanceRecord::query()->create([
            'branch_id' => $plan['branch_id'],
            'shift_assignment_id' => $assignment->getKey(),
            'employee_id' => $plan['user']->getKey(),
            'work_date' => $date->toDateString(),
            'shift_name' => $assignment->shift_name,
            'shift_value' => $assignment->shift_value,
            'status' => $late ? AttendanceStatus::Late : AttendanceStatus::Present,
            'checked_in_at' => $checkedIn,
            'checked_out_at' => $checkedOut,
        ]);

        $record->forceFill([
            'source' => AttendanceSource::Gps,
            'check_in_latitude' => null,
            'check_in_longitude' => null,
            'check_in_accuracy_meters' => 8 + ($roll % 22),
            'check_in_distance_meters' => 5 + ($roll % 40),
            'check_in_ip' => '192.168.1.'.(20 + ($roll % 60)),
            'check_in_user_agent' => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/127 Mobile Safari/537.36',
            'check_in_verification' => GpsVerification::Verified,
            'late_minutes' => $late ? (int) floor($assignment->planned_start_at->diffInSeconds($checkedIn) / 60) : 0,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_status' => $overtimeMinutes > 0 ? OvertimeStatus::Pending : OvertimeStatus::None,
        ])->save();

        if ($checkedOut !== null) {
            $record->forceFill([
                'check_out_accuracy_meters' => 8 + ($roll % 18),
                'check_out_distance_meters' => 5 + ($roll % 35),
                'check_out_ip' => $record->check_in_ip,
                'check_out_user_agent' => $record->check_in_user_agent,
                'check_out_verification' => GpsVerification::Verified,
            ])->save();
        }

        $this->log($record, $plan['user'], AttendanceAuditAction::CheckIn, null, $record->auditSnapshot(),
            sprintf('Vào ca bằng GPS, cách cửa hàng %d mét.', $record->check_in_distance_meters));

        if ($checkedOut !== null) {
            $this->log($record, $plan['user'], AttendanceAuditAction::CheckOut, null, $record->auditSnapshot(),
                $overtimeMinutes > 0
                    ? sprintf('Ra ca bằng GPS, vượt ca %d phút và đang chờ duyệt.', $overtimeMinutes)
                    : 'Ra ca bằng GPS.');
        }

        $counters[$late ? 'late' : 'present']++;

        if ($forgotCheckOut) {
            $counters['open']++;
        }

        if ($overtimeMinutes > 0) {
            $counters['overtime']++;

            // Tăng ca của tuần trước đã được duyệt; tuần này còn treo để xem
            // được cả hai trạng thái trên màn hình.
            if ($date->lt(CarbonImmutable::today()->startOfWeek())) {
                $this->approve($record, $plan['branch_id'], $overtimeMinutes);
            }
        }
    }

    /** Nghỉ có báo trước: quản lý ghi lại, không có giờ vào ra. */
    private function leaveDay(array $plan, ShiftAssignment $assignment, CarbonImmutable $date, array &$counters): void
    {
        $approver = $this->approver($plan['branch_id']);

        $record = AttendanceRecord::query()->create([
            'branch_id' => $plan['branch_id'],
            'shift_assignment_id' => $assignment->getKey(),
            'employee_id' => $plan['user']->getKey(),
            'work_date' => $date->toDateString(),
            'shift_name' => $assignment->shift_name,
            'shift_value' => 0,
            'status' => AttendanceStatus::Leave,
            'note' => 'Nghỉ có báo trước',
        ]);

        $record->forceFill(['source' => AttendanceSource::Manual])->save();

        $this->log($record, $approver, AttendanceAuditAction::ManualCreate, null, $record->auditSnapshot(),
            'Nhân viên xin nghỉ, đã báo trước 3 ngày.');

        $counters['leave']++;
    }

    private function approve(AttendanceRecord $record, int $branchId, int $minutes): void
    {
        $approver = $this->approver($branchId);
        $before = $record->auditSnapshot();

        $record->forceFill([
            'overtime_status' => OvertimeStatus::Approved,
            'approved_overtime_minutes' => $minutes,
            'overtime_approved_by' => $approver?->getKey(),
            'overtime_approved_at' => $record->checked_out_at?->copy()->addDay(),
            'overtime_approval_note' => 'Làm nốt khách nên quá ca.',
        ])->save();

        $this->log($record, $approver, AttendanceAuditAction::OvertimeApproved, $before, $record->auditSnapshot(),
            'Làm nốt khách nên quá ca.');
    }

    /** Người duyệt: quản lý của chi nhánh, không có thì tới chủ tiệm. */
    private function approver(int $branchId): ?User
    {
        static $cache = [];

        return $cache[$branchId] ??= User::query()
            ->where('role', 'manager')
            ->whereHas('branchAssignments', fn ($query) => $query->where('branch_id', $branchId))
            ->first()
            ?? User::query()->where('role', 'owner')->first();
    }

    /** @param  array<string, mixed>|null  $before */
    private function log(AttendanceRecord $record, ?User $actor, AttendanceAuditAction $action, ?array $before, array $after, string $reason): void
    {
        // Qua service dùng chung để phần snapshot (nhân viên, ngày, tên ca)
        // chỉ có một chỗ quyết định, và dữ liệu mẫu giống hệt dữ liệu thật.
        app(AttendanceAuditor::class)->record($record, $actor, $action, $before, $after, $reason);
    }

    /**
     * Con số tất định cho một người vào một ngày.
     *
     * Dùng thay cho random để chạy lại cho ra đúng dữ liệu cũ.
     */
    private function roll(int $userId, CarbonImmutable $date): int
    {
        return (int) (crc32($userId.':'.$date->toDateString()) % 1000);
    }

    /**
     * Toạ độ tạm để bấm thử nút Vào ca.
     *
     * Đây là điểm gần đúng của tên đường, KHÔNG phải vị trí thật của tiệm.
     * Phải thay bằng toạ độ thật trước khi cho nhân viên dùng.
     */
    private function fillPlaceholderCoordinates(): void
    {
        $placeholders = [
            'CN-01' => [21.0122000, 105.8180000],
            'CN-02' => [20.9822000, 105.8430000],
        ];

        foreach ($placeholders as $code => [$latitude, $longitude]) {
            $branch = Branch::query()->where('code', $code)->first();

            if ($branch === null || $branch->latitude !== null) {
                continue;
            }

            $branch->forceFill([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'gps_attendance_enabled' => true,
            ])->save();

            $this->warn(sprintf(
                '%s: đã điền toạ độ TẠM (%s, %s). Hãy thay bằng vị trí thật của tiệm ở màn hình Chi nhánh.',
                $code, $latitude, $longitude,
            ));
        }
    }
}
