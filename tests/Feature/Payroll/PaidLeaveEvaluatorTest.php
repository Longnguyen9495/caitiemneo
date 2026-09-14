<?php

namespace Tests\Feature\Payroll;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\MonthlyPaidLeaveDay;
use App\Models\User;
use App\Services\Payroll\PaidLeaveEvaluator;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaidLeaveEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('calendarMonths')]
    public function test_it_snapshots_actual_calendar_and_required_work_days(string $periodStart, string $periodEnd, int $calendarDays): void
    {
        $employee = User::factory()->employee()->create();
        $branch = Branch::factory()->create();

        $this->planPaidLeave($employee, $branch, $periodStart);
        $this->planPaidLeave($employee, $branch, $periodEnd);

        $facts = app(PaidLeaveEvaluator::class)->evaluate(
            $employee->id,
            now()->parse($periodStart),
            now()->parse($periodEnd),
            Money::toMinor(3_100_000),
        );

        $this->assertSame($calendarDays, $facts['calendar_days']);
        $this->assertSame(2, $facts['paid_leave_days']);
        $this->assertSame($calendarDays - 2, $facts['required_work_days']);
        $this->assertSame(intdiv(Money::toMinor(3_100_000), $calendarDays), $facts['daily_base_salary_rate_minor']);
    }

    public static function calendarMonths(): array
    {
        return [
            '28 days' => ['2026-02-01', '2026-02-28', 28],
            '29 days' => ['2028-02-01', '2028-02-29', 29],
            '30 days' => ['2026-04-01', '2026-04-30', 30],
            '31 days' => ['2026-08-01', '2026-08-31', 31],
        ];
    }

    public function test_working_on_a_planned_paid_leave_day_adds_the_fixed_bonus_without_a_deduction(): void
    {
        $employee = User::factory()->employee()->create();
        $branch = Branch::factory()->create();

        $this->planPaidLeave($employee, $branch, '2026-08-05');
        $this->planPaidLeave($employee, $branch, '2026-08-12');
        $this->attendance($employee, $branch, '2026-08-05', AttendanceStatus::Present);

        $facts = app(PaidLeaveEvaluator::class)->evaluate(
            $employee->id,
            now()->parse('2026-08-01'),
            now()->parse('2026-08-31'),
            Money::toMinor(3_100_000),
        );

        $this->assertSame(1, $facts['worked_paid_leave_days']);
        $this->assertSame(Money::toMinor(200_000), $facts['worked_paid_leave_bonus_minor']);
        $this->assertSame(0, $facts['unpaid_leave_days']);
        $this->assertSame(0, $facts['unpaid_leave_deduction_minor']);
    }

    public function test_leave_or_absence_outside_the_planned_paid_leave_days_is_deducted(): void
    {
        $employee = User::factory()->employee()->create();
        $branch = Branch::factory()->create();

        $this->planPaidLeave($employee, $branch, '2026-08-05');
        $this->planPaidLeave($employee, $branch, '2026-08-12');
        $this->attendance($employee, $branch, '2026-08-05', AttendanceStatus::Leave);
        $this->attendance($employee, $branch, '2026-08-20', AttendanceStatus::Absent);

        $facts = app(PaidLeaveEvaluator::class)->evaluate(
            $employee->id,
            now()->parse('2026-08-01'),
            now()->parse('2026-08-31'),
            Money::toMinor(3_100_000),
        );

        $this->assertSame(1, $facts['unpaid_leave_days']);
        $this->assertSame(Money::toMinor(100_000), $facts['daily_base_salary_rate_minor']);
        $this->assertSame(Money::toMinor(100_000), $facts['unpaid_leave_deduction_minor']);
        $this->assertSame(Money::toMinor(3_000_000), $facts['net_base_salary_minor']);
    }

    private function planPaidLeave(User $employee, Branch $branch, string $date): void
    {
        MonthlyPaidLeaveDay::factory()->create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'leave_date' => $date,
        ]);
    }

    private function attendance(User $employee, Branch $branch, string $date, AttendanceStatus $status): void
    {
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'work_date' => $date,
            'status' => $status,
        ]);
    }
}
