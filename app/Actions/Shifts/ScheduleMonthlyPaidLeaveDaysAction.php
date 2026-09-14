<?php

namespace App\Actions\Shifts;

use App\Enums\AuditAction;
use App\Models\Branch;
use App\Models\MonthlyPaidLeaveDay;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Payroll\PayrollLockGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleMonthlyPaidLeaveDaysAction
{
    public const DAYS_PER_MONTH = 2;

    public function __construct(
        private AuditRecorder $audit,
        private PayrollLockGuard $payrollLock,
    ) {}

    /** @param array<int, string> $dates */
    public function handle(User $actor, User $employee, Branch $branch, string $month, array $dates): void
    {
        $start = CarbonImmutable::parse($month)->startOfMonth();
        $normalisedDates = collect($dates)
            ->filter()
            ->map(fn (string $date): string => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->sort()
            ->values();

        if ($normalisedDates->count() !== self::DAYS_PER_MONTH) {
            throw ValidationException::withMessages(['leave_dates' => 'Mỗi nhân viên phải được xếp đúng 2 ngày nghỉ hưởng lương trong tháng.']);
        }

        if ($normalisedDates->contains(fn (string $date): bool => ! CarbonImmutable::parse($date)->isSameMonth($start))) {
            throw ValidationException::withMessages(['leave_dates' => 'Ngày nghỉ phải nằm trong tháng đang xếp.']);
        }

        DB::transaction(function () use ($actor, $employee, $branch, $start, $normalisedDates): void {
            if ((! $actor->isOwner() && ! $actor->isManager()) || ! $actor->canAccessBranch($branch, $start)) {
                throw ValidationException::withMessages(['branch_id' => 'Bạn không có quyền xếp ngày nghỉ tại chi nhánh này.']);
            }

            if (! $employee->is_active || ! $employee->canAccessBranch($branch, $start)) {
                throw ValidationException::withMessages(['employee_id' => 'Nhân viên không thuộc chi nhánh vào tháng đang xếp.']);
            }

            $existing = MonthlyPaidLeaveDay::query()
                ->where('employee_id', $employee->getKey())
                ->inMonth($start)
                ->lockForUpdate()
                ->get();

            $lockedDates = $existing
                ->pluck('leave_date')
                ->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())
                ->merge($normalisedDates)
                ->unique();

            $lockedDates->each(fn (string $date) => $this->payrollLock->assertUnlocked(
                $employee->getKey(),
                $date,
                'leave_dates',
            ));

            $existing->each(function (MonthlyPaidLeaveDay $paidLeaveDay) use ($actor, $branch): void {
                $before = $paidLeaveDay->getAttributes();
                $paidLeaveDay->delete();
                $this->audit->record($paidLeaveDay, $actor, AuditAction::Deleted, $before, null, 'Cập nhật lịch nghỉ hưởng lương theo tháng.', $branch->getKey());
            });

            $normalisedDates->each(function (string $date) use ($actor, $employee, $branch): void {
                $paidLeaveDay = MonthlyPaidLeaveDay::query()->create([
                    'employee_id' => $employee->getKey(),
                    'branch_id' => $branch->getKey(),
                    'leave_date' => $date,
                    'scheduled_by' => $actor->getKey(),
                ]);

                $this->audit->record($paidLeaveDay, $actor, AuditAction::Created, null, $paidLeaveDay->getAttributes(), 'Xếp ngày nghỉ hưởng lương theo tháng.', $branch->getKey());
            });
        });
    }
}
