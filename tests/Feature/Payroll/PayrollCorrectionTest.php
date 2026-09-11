<?php

namespace Tests\Feature\Payroll;

use App\Actions\Payrolls\CalculatePayrollAction;
use App\Enums\PaymentMethod;
use App\Enums\PayrollAdjustmentCategory;
use App\Enums\PayrollAdjustmentDirection;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPolicy;
use App\Models\PendingPayrollCorrection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $employee;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'CN-C']);
        PayrollPolicy::factory()->create(['attendance_bonus_amount' => 0, 'allowed_absence_days' => 31]);
        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
        $this->owner = User::factory()->owner()->create();
    }

    private function paidInvoiceWithCommission(int $commission = 150000): Invoice
    {
        $invoice = Invoice::factory()->create(['branch_id' => $this->branch->id]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'employee_id' => $this->employee->id,
            'unit_price' => 1000000,
            'quantity' => 1,
            'commission_rate' => 15,
            'commission_amount' => $commission,
        ]);

        $this->actingAs($this->owner)->post(route('admin.invoices.pay', $invoice), [
            'payment_method' => PaymentMethod::Cash->value,
        ]);

        return $invoice->fresh();
    }

    private function payrollFor(string $start, string $end): Payroll
    {
        return app(CalculatePayrollAction::class)->refresh(
            Payroll::factory()->create([
                'employee_id' => $this->employee->id,
                'paying_branch_id' => $this->branch->id,
                'period_start' => $start,
                'period_end' => $end,
            ])
        );
    }

    public function test_a_refund_on_a_draft_payroll_just_recalculates(): void
    {
        $invoice = $this->paidInvoiceWithCommission();
        $payroll = $this->payrollFor(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

        $this->assertSame('150000.00', $payroll->regular_commission_pay);

        $this->actingAs($this->owner)->delete(route('admin.invoices.cancel', $invoice), ['cancel_reason' => 'Khách đổi ý']);

        app(CalculatePayrollAction::class)->refresh($payroll);

        $this->assertSame('0.00', $payroll->fresh()->regular_commission_pay);
        $this->assertSame(0, PendingPayrollCorrection::query()->count());
    }

    public function test_a_refund_after_finalising_becomes_a_correction_in_the_next_period(): void
    {
        $invoice = $this->paidInvoiceWithCommission();

        $closed = $this->payrollFor(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        $closedTotal = $closed->final_total;

        $this->actingAs($this->owner)->post(route('admin.payrolls.finalize', $closed))->assertRedirect();

        $this->actingAs($this->owner)->delete(route('admin.invoices.cancel', $invoice), ['cancel_reason' => 'Hoàn tiền sau khi chốt lương']);

        // The closed payroll is untouched.
        $this->assertSame($closedTotal, $closed->fresh()->final_total);

        $pending = PendingPayrollCorrection::query()->firstOrFail();
        $this->assertSame('150000.00', $pending->amount);
        $this->assertSame(PayrollAdjustmentDirection::Deduction, $pending->direction);
        $this->assertSame($closed->id, $pending->origin_payroll_id);

        // The next period picks it up as a visible line.
        $next = $this->payrollFor(
            now()->addMonth()->startOfMonth()->toDateString(),
            now()->addMonth()->endOfMonth()->toDateString(),
        );

        $correction = PayrollAdjustment::query()
            ->where('payroll_id', $next->id)
            ->where('category', PayrollAdjustmentCategory::Correction)
            ->firstOrFail();

        $this->assertSame('150000.00', $correction->amount);
        $this->assertSame(PayrollAdjustmentDirection::Deduction, $correction->direction);
        $this->assertSame($next->id, $pending->fresh()->applied_payroll_id);
    }

    public function test_processing_the_same_refund_twice_creates_one_correction(): void
    {
        $invoice = $this->paidInvoiceWithCommission();
        $closed = $this->payrollFor(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        $this->actingAs($this->owner)->post(route('admin.payrolls.finalize', $closed));

        $this->actingAs($this->owner)->delete(route('admin.invoices.cancel', $invoice), ['cancel_reason' => 'Lần một']);
        $this->actingAs($this->owner)->delete(route('admin.invoices.cancel', $invoice), ['cancel_reason' => 'Lần hai']);

        $this->assertSame(1, PendingPayrollCorrection::query()->count());
    }

    public function test_recalculating_the_next_payroll_does_not_duplicate_the_correction(): void
    {
        $invoice = $this->paidInvoiceWithCommission();
        $closed = $this->payrollFor(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        $this->actingAs($this->owner)->post(route('admin.payrolls.finalize', $closed));
        $this->actingAs($this->owner)->delete(route('admin.invoices.cancel', $invoice), ['cancel_reason' => 'Hoàn tiền']);

        $next = $this->payrollFor(
            now()->addMonth()->startOfMonth()->toDateString(),
            now()->addMonth()->endOfMonth()->toDateString(),
        );

        app(CalculatePayrollAction::class)->refresh($next);
        app(CalculatePayrollAction::class)->refresh($next->fresh());

        $this->assertSame(
            1,
            PayrollAdjustment::query()
                ->where('payroll_id', $next->id)
                ->where('category', PayrollAdjustmentCategory::Correction)
                ->count()
        );

        $this->assertSame('-150000.00', $next->fresh()->calculated_total);
    }
}
