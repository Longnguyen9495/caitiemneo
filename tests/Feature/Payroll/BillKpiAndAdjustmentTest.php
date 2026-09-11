<?php

namespace Tests\Feature\Payroll;

use App\Actions\Payrolls\CalculatePayrollAction;
use App\Enums\InvoiceStatus;
use App\Enums\PayrollAdjustmentCategory;
use App\Enums\PayrollAdjustmentDirection;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillKpiAndAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'CN-B1']);
        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
    }

    private function policy(int $required, int $reward, ?int $penalty = null): PayrollPolicy
    {
        return PayrollPolicy::factory()
            ->withBillKpi($required, $reward, $penalty)
            ->create(['attendance_bonus_amount' => 0, 'allowed_absence_days' => 31]);
    }

    private function qualifiedInvoice(bool $qualified = true, InvoiceStatus $status = InvoiceStatus::Paid, int $lines = 1): Invoice
    {
        $invoice = Invoice::factory()->create([
            'branch_id' => $this->branch->id,
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::Paid ? '2026-08-10 10:00:00' : null,
            'qualified_for_bill_kpi' => $qualified,
        ]);

        for ($i = 0; $i < $lines; $i++) {
            InvoiceItem::factory()->create([
                'invoice_id' => $invoice->id,
                'employee_id' => $this->employee->id,
                'line_total' => 100000,
            ]);
        }

        return $invoice;
    }

    private function calculate(): Payroll
    {
        return app(CalculatePayrollAction::class)->refresh(
            Payroll::factory()->create([
                'employee_id' => $this->employee->id,
                'paying_branch_id' => $this->branch->id,
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
            ])
        );
    }

    public function test_reaching_the_bill_target_pays_the_reward(): void
    {
        $this->policy(2, 300000);
        $this->qualifiedInvoice();
        $this->qualifiedInvoice();

        $payroll = $this->calculate();

        $this->assertSame(2, $payroll->qualified_bill_count);
        $this->assertTrue($payroll->bill_kpi_achieved);
        $this->assertSame('300000.00', $payroll->bill_kpi_bonus);
    }

    public function test_missing_the_target_only_withholds_the_bonus_when_no_penalty_is_configured(): void
    {
        $this->policy(3, 300000);
        $this->qualifiedInvoice();

        $payroll = $this->calculate();

        $this->assertFalse($payroll->bill_kpi_achieved);
        $this->assertSame('0.00', $payroll->bill_kpi_bonus);
        $this->assertSame(
            0,
            PayrollAdjustment::query()
                ->where('payroll_id', $payroll->id)
                ->where('category', PayrollAdjustmentCategory::MissingBillPenalty)
                ->count()
        );
    }

    public function test_a_configured_penalty_is_applied_when_the_target_is_missed(): void
    {
        $this->policy(3, 300000, 100000);
        $this->qualifiedInvoice();

        $payroll = $this->calculate();

        $penalty = PayrollAdjustment::query()
            ->where('payroll_id', $payroll->id)
            ->where('category', PayrollAdjustmentCategory::MissingBillPenalty)
            ->firstOrFail();

        $this->assertSame('100000.00', $penalty->amount);
        $this->assertSame(PayrollAdjustmentDirection::Deduction, $penalty->direction);
    }

    public function test_one_invoice_is_counted_once_however_many_lines_it_has(): void
    {
        $this->policy(2, 300000);
        $this->qualifiedInvoice(lines: 4);

        $payroll = $this->calculate();

        $this->assertSame(1, $payroll->qualified_bill_count);
        $this->assertFalse($payroll->bill_kpi_achieved);
    }

    public function test_unverified_and_unpaid_invoices_are_not_counted(): void
    {
        $this->policy(1, 300000);
        $this->qualifiedInvoice(qualified: false);
        $this->qualifiedInvoice(qualified: true, status: InvoiceStatus::Draft);

        $this->assertSame(0, $this->calculate()->qualified_bill_count);
    }

    public function test_only_leadership_may_verify_an_invoice_for_the_bill_kpi(): void
    {
        $invoice = $this->qualifiedInvoice(qualified: false);
        $staff = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create(['can_create_invoices' => true]);

        $this->actingAs($staff)
            ->patch(route('admin.invoices.bill-kpi', $invoice), ['qualified_for_bill_kpi' => 1])
            ->assertForbidden();

        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();

        $this->actingAs($manager)
            ->patch(route('admin.invoices.bill-kpi', $invoice), ['qualified_for_bill_kpi' => 1])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertTrue($invoice->qualified_for_bill_kpi);
        $this->assertSame($manager->id, $invoice->bill_kpi_verified_by);
        $this->assertNotNull($invoice->bill_kpi_verified_at);
    }

    public function test_recalculating_replaces_engine_rows_and_keeps_manual_ones(): void
    {
        $this->policy(1, 300000);
        $this->qualifiedInvoice();

        $payroll = $this->calculate();

        PayrollAdjustment::query()->create([
            'payroll_id' => $payroll->id,
            'branch_id' => $this->branch->id,
            'category' => PayrollAdjustmentCategory::Allowance,
            'direction' => PayrollAdjustmentDirection::Earning,
            'amount' => 300000,
            'description' => 'Phụ cấp xăng xe',
            'is_automatic' => false,
        ]);

        app(CalculatePayrollAction::class)->refresh($payroll);
        app(CalculatePayrollAction::class)->refresh($payroll->fresh());

        $this->assertSame(
            1,
            PayrollAdjustment::query()->where('payroll_id', $payroll->id)->where('category', PayrollAdjustmentCategory::BillKpiBonus)->count(),
            'Khoản tự động bị nhân bản sau khi tính lại.'
        );

        $this->assertSame(
            1,
            PayrollAdjustment::query()->where('payroll_id', $payroll->id)->where('category', PayrollAdjustmentCategory::Allowance)->count(),
            'Khoản nhập tay bị mất sau khi tính lại.'
        );

        $this->assertSame('300000.00', $payroll->fresh()->allowance_total);
    }
}
