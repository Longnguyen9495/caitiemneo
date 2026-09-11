<?php

namespace Tests\Feature\Payroll;

use App\Actions\Payrolls\CalculatePayrollAction;
use App\Enums\AttendanceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PayrollAdjustmentCategory;
use App\Enums\WorkContext;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\EmployeeCompensationProfile;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollRulesTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'CN-P']);
    }

    private function employee(): User
    {
        return User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
    }

    private function payrollFor(User $employee): Payroll
    {
        return app(CalculatePayrollAction::class)->refresh(
            Payroll::factory()->create([
                'employee_id' => $employee->id,
                'paying_branch_id' => $this->branch->id,
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
            ])
        );
    }

    private function shift(User $employee, string $date, AttendanceStatus $status = AttendanceStatus::Present, float $value = 1): void
    {
        AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'work_date' => $date,
            'shift_name' => 'Ca '.$date,
            'shift_value' => $value,
            'status' => $status,
        ]);
    }

    public function test_a_clean_attendance_record_earns_the_bonus(): void
    {
        PayrollPolicy::factory()->create(['attendance_bonus_amount' => 200000, 'allowed_absence_days' => 2]);
        $employee = $this->employee();

        $this->shift($employee, '2026-08-01');
        $this->shift($employee, '2026-08-02', AttendanceStatus::Absent);

        $payroll = $this->payrollFor($employee);

        $this->assertSame('200000.00', $payroll->attendance_bonus);
        $this->assertSame(
            1,
            PayrollAdjustment::query()
                ->where('payroll_id', $payroll->id)
                ->where('category', PayrollAdjustmentCategory::AttendanceBonus)
                ->count()
        );
    }

    public function test_too_many_absences_lose_the_bonus(): void
    {
        PayrollPolicy::factory()->create(['attendance_bonus_amount' => 200000, 'allowed_absence_days' => 1]);
        $employee = $this->employee();

        $this->shift($employee, '2026-08-01', AttendanceStatus::Absent);
        $this->shift($employee, '2026-08-02', AttendanceStatus::Absent);

        $this->assertSame('0.00', $this->payrollFor($employee)->attendance_bonus);
    }

    public function test_whether_approved_leave_breaks_the_streak_is_a_policy_choice(): void
    {
        PayrollPolicy::factory()->create([
            'attendance_bonus_amount' => 200000,
            'allowed_absence_days' => 0,
            'excused_leave_counts_as_absence' => true,
        ]);

        $employee = $this->employee();
        $this->shift($employee, '2026-08-01', AttendanceStatus::Leave);

        $this->assertSame('0.00', $this->payrollFor($employee)->attendance_bonus);
    }

    public function test_commission_uses_the_rate_snapshotted_on_each_line(): void
    {
        PayrollPolicy::factory()->create(['attendance_bonus_amount' => 0, 'allowed_absence_days' => 31]);
        $employee = $this->employee();

        $invoice = Invoice::factory()->create([
            'branch_id' => $this->branch->id,
            'status' => InvoiceStatus::Paid,
            'paid_at' => '2026-08-10 10:00:00',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'employee_id' => $employee->id,
            'work_context' => WorkContext::Regular,
            'line_total' => 1000000,
            'commission_rate' => 15,
            'commission_amount' => 150000,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'employee_id' => $employee->id,
            'work_context' => WorkContext::Overtime,
            'line_total' => 500000,
            'commission_rate' => 20,
            'commission_amount' => 100000,
        ]);

        $payroll = $this->payrollFor($employee);

        $this->assertSame('1000000.00', $payroll->regular_revenue);
        $this->assertSame('150000.00', $payroll->regular_commission_pay);
        $this->assertSame('500000.00', $payroll->overtime_revenue);
        $this->assertSame('100000.00', $payroll->overtime_commission_pay);
        $this->assertSame('250000.00', $payroll->commission_pay);
    }

    public function test_a_branch_profile_beats_the_company_wide_one(): void
    {
        PayrollPolicy::factory()->create(['attendance_bonus_amount' => 0, 'allowed_absence_days' => 31]);
        $employee = $this->employee();

        EmployeeCompensationProfile::query()->create([
            'user_id' => $employee->id,
            'branch_id' => $this->branch->id,
            'base_salary' => 7000000,
            'shift_rate' => 300000,
            'regular_commission_rate' => 18,
            'overtime_commission_rate' => 25,
            'effective_from' => '2026-01-01',
        ]);

        $this->shift($employee, '2026-08-01');

        $payroll = $this->payrollFor($employee);

        $this->assertSame('300000.00', $payroll->shift_pay);
    }

    public function test_the_profile_in_force_on_the_period_is_the_one_used(): void
    {
        PayrollPolicy::factory()->create(['attendance_bonus_amount' => 0, 'allowed_absence_days' => 31]);
        $employee = $this->employee();

        $employee->compensationProfiles()->update(['effective_to' => '2026-07-31']);

        EmployeeCompensationProfile::query()->create([
            'user_id' => $employee->id,
            'branch_id' => null,
            'base_salary' => 9000000,
            'shift_rate' => 400000,
            'regular_commission_rate' => 10,
            'overtime_commission_rate' => 12,
            'effective_from' => '2026-08-01',
        ]);

        $this->shift($employee, '2026-08-05');

        $payroll = $this->payrollFor($employee);

        $this->assertSame('9000000.00', $payroll->base_salary);
        $this->assertSame('400000.00', $payroll->shift_pay);
    }
}
