<?php

namespace Tests\Feature\Payroll;

use App\Actions\Payrolls\CalculatePayrollAction;
use App\Enums\AttendanceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PayrollStatus;
use App\Enums\WorkContext;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payroll;
use App\Models\PayrollPolicy;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAllocationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::factory()->create(['code' => 'CN-AA']);
        $this->branchB = Branch::factory()->create(['code' => 'CN-BB']);

        PayrollPolicy::factory()->create([
            'attendance_bonus_amount' => 200000,
            'allowed_absence_days' => 5,
        ]);

        // Works three shifts at A and one at B during the period.
        $this->employee = User::factory()->employee()->withoutBranch()
            ->atBranch($this->branchA)
            ->atBranch($this->branchB, false)
            ->create(['base_salary' => 4000000, 'shift_rate' => 200000, 'commission_rate' => 15]);

        foreach (['2026-08-01', '2026-08-02', '2026-08-03'] as $date) {
            $this->shift($this->branchA, $date);
        }

        $this->shift($this->branchB, '2026-08-04');
    }

    private function shift(Branch $branch, string $date): void
    {
        AttendanceRecord::factory()->create([
            'branch_id' => $branch->id,
            'employee_id' => $this->employee->id,
            'work_date' => $date,
            'shift_name' => 'Ca '.$date,
            'shift_value' => 1,
            'status' => AttendanceStatus::Present,
        ]);
    }

    private function revenue(Branch $branch, int $lineTotal, int $commission): void
    {
        $invoice = Invoice::factory()->create([
            'branch_id' => $branch->id,
            'status' => InvoiceStatus::Paid,
            'paid_at' => '2026-08-05 10:00:00',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'employee_id' => $this->employee->id,
            'work_context' => WorkContext::Regular,
            'line_total' => $lineTotal,
            'commission_amount' => $commission,
        ]);
    }

    private function calculate(): Payroll
    {
        return app(CalculatePayrollAction::class)->refresh(
            Payroll::factory()->create([
                'employee_id' => $this->employee->id,
                'paying_branch_id' => $this->branchA->id,
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
            ])
        );
    }

    public function test_each_branch_carries_the_work_that_happened_there(): void
    {
        $this->revenue($this->branchA, 2000000, 300000);
        $this->revenue($this->branchB, 1000000, 150000);

        $payroll = $this->calculate();

        $allocationA = $payroll->allocations()->where('branch_id', $this->branchA->id)->firstOrFail();
        $allocationB = $payroll->allocations()->where('branch_id', $this->branchB->id)->firstOrFail();

        $this->assertSame('3.00', $allocationA->shift_count);
        $this->assertSame('1.00', $allocationB->shift_count);
        $this->assertSame('600000.00', $allocationA->shift_pay);
        $this->assertSame('200000.00', $allocationB->shift_pay);
        $this->assertSame('300000.00', $allocationA->regular_commission_pay);
        $this->assertSame('150000.00', $allocationB->regular_commission_pay);
    }

    public function test_period_wide_money_is_split_by_shift_volume_without_losing_a_dong(): void
    {
        $payroll = $this->calculate();

        $baseShares = $payroll->allocations->sum(fn ($row) => Money::toMinor($row->base_salary_amount));
        $bonusShares = $payroll->allocations->sum(fn ($row) => Money::toMinor($row->attendance_bonus));

        $this->assertSame(Money::toMinor('4000000'), $baseShares);
        $this->assertSame(Money::toMinor('200000'), $bonusShares);

        // Three quarters of the shifts were at A.
        $this->assertSame('3000000.00', $payroll->allocations->firstWhere('branch_id', $this->branchA->id)->base_salary_amount);
        $this->assertSame('1000000.00', $payroll->allocations->firstWhere('branch_id', $this->branchB->id)->base_salary_amount);
    }

    public function test_the_allocations_add_up_to_the_payroll_total(): void
    {
        $this->revenue($this->branchA, 2000000, 300000);
        $this->revenue($this->branchB, 1000000, 150000);

        $payroll = $this->calculate();

        $sum = $payroll->allocations->sum(fn ($row) => Money::toMinor($row->subtotal));

        $this->assertSame(Money::toMinor($payroll->calculated_total), $sum);

        // 4.000.000 lương cứng + 800.000 tiền ca + 450.000 hoa hồng + 200.000 chuyên cần.
        $this->assertSame('5450000.00', $payroll->calculated_total);
    }

    public function test_a_finalized_payroll_does_not_move_when_the_source_data_changes(): void
    {
        $owner = User::factory()->owner()->create();
        $payroll = $this->calculate();
        $before = $payroll->final_total;

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.finalize', $payroll))->assertRedirect();

        // New revenue and a policy change land after the period was closed.
        $this->revenue($this->branchA, 9000000, 1350000);
        PayrollPolicy::query()->update(['attendance_bonus_amount' => 999000]);

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.recalculate', $payroll))->assertForbidden();

        $this->assertSame($before, $payroll->fresh()->final_total);
        $this->assertSame(PayrollStatus::Finalized, $payroll->fresh()->status);
    }

    public function test_payment_creates_one_expense_in_the_paying_branch(): void
    {
        $owner = User::factory()->owner()->create();
        $payroll = $this->calculate();

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.finalize', $payroll));
        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.pay', $payroll), ['payment_method' => PaymentMethod::Transfer->value]);
        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.pay', $payroll), ['payment_method' => PaymentMethod::Cash->value]);

        $transactions = CashTransaction::query()->where('payroll_id', $payroll->id)->get();

        $this->assertCount(1, $transactions);
        $this->assertSame($this->branchA->id, $transactions->first()->branch_id);
        $this->assertSame($payroll->fresh()->final_total, $transactions->first()->amount);
    }
}
