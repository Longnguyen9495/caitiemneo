<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PayrollStatus;
use App\Models\AttendanceRecord;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An employee with 2.5 payable shifts and one commission inside August 2026,
     * plus earnings that must stay out of the period.
     */
    private function employeeWithEarnings(): User
    {
        $employee = User::factory()->employee()->create([
            'base_salary' => 3000000,
            'shift_rate' => 200000,
            'commission_rate' => 10,
        ]);

        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-08-05',
            'shift_name' => 'Ca sáng',
            'shift_value' => 1,
        ]);

        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-08-06',
            'shift_name' => 'Ca chiều',
            'shift_value' => 1.5,
            'status' => AttendanceStatus::Late,
        ]);

        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-08-07',
            'shift_name' => 'Ca nghỉ',
            'shift_value' => 1,
            'status' => AttendanceStatus::Leave,
        ]);

        $this->commission($employee, 400000, InvoiceStatus::Paid, '2026-08-10 12:00:00');
        $this->commission($employee, 999000, InvoiceStatus::Paid, '2026-09-02 12:00:00');
        $this->commission($employee, 777000, InvoiceStatus::Draft, null);

        return $employee;
    }

    private function commission(User $employee, int $amount, InvoiceStatus $status, ?string $paidAt): void
    {
        $invoice = Invoice::factory()->create(['status' => $status, 'paid_at' => $paidAt]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'employee_id' => $employee->id,
            'commission_amount' => $amount,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(User $employee, array $extra = []): array
    {
        return array_merge([
            'employee_id' => $employee->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ], $extra);
    }

    public function test_a_payroll_sums_base_shift_and_commission_for_the_period(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = $this->employeeWithEarnings();

        $this->actingAs($owner)->withConfirmedPassword()
            ->post(route('admin.payrolls.store'), $this->payload($employee, ['adjustment' => 100000, 'deduction' => 50000]))
            ->assertRedirect();

        $payroll = Payroll::query()->firstOrFail();

        $this->assertSame('3000000.00', $payroll->base_salary);
        $this->assertSame('2.50', $payroll->shift_count);
        $this->assertSame('200000.00', $payroll->shift_rate);
        $this->assertSame('500000.00', $payroll->shift_pay);
        $this->assertSame('400000.00', $payroll->commission_pay);
        $this->assertSame('3950000.00', $payroll->total);
    }

    public function test_overlapping_periods_for_the_same_employee_are_rejected(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = $this->employeeWithEarnings();

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.store'), $this->payload($employee));

        $this->actingAs($owner)->withConfirmedPassword()
            ->from(route('admin.payrolls.create'))
            ->post(route('admin.payrolls.store'), $this->payload($employee, ['period_start' => '2026-08-15', 'period_end' => '2026-09-15']))
            ->assertSessionHasErrors('period_start');

        $this->assertSame(1, Payroll::query()->count());
    }

    public function test_paying_a_payroll_creates_exactly_one_expense_transaction(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = $this->employeeWithEarnings();

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.store'), $this->payload($employee));
        $payroll = Payroll::query()->firstOrFail();

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.finalize', $payroll))->assertRedirect();
        $this->assertSame(PayrollStatus::Finalized, $payroll->fresh()->status);

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.pay', $payroll), ['payment_method' => PaymentMethod::Transfer->value]);
        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.pay', $payroll), ['payment_method' => PaymentMethod::Cash->value]);

        $payroll->refresh();
        $this->assertSame(PayrollStatus::Paid, $payroll->status);

        $transactions = CashTransaction::query()->where('payroll_id', $payroll->id)->get();
        $this->assertCount(1, $transactions);
        $this->assertSame(CashTransactionType::Expense, $transactions->first()->type);
        $this->assertSame(CashTransactionCategory::Payroll, $transactions->first()->category);
        $this->assertSame($payroll->total, $transactions->first()->amount);
    }

    public function test_a_finalized_payroll_cannot_be_edited(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = $this->employeeWithEarnings();

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.store'), $this->payload($employee));
        $payroll = Payroll::query()->firstOrFail();
        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.finalize', $payroll));

        $this->actingAs($owner)->withConfirmedPassword()
            ->patch(route('admin.payrolls.update', $payroll), ['adjustment' => 999000])
            ->assertForbidden();

        $this->assertSame('0.00', $payroll->fresh()->adjustment);
    }

    public function test_a_payroll_cannot_be_paid_before_it_is_finalized(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = $this->employeeWithEarnings();

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.store'), $this->payload($employee));
        $payroll = Payroll::query()->firstOrFail();

        $this->actingAs($owner)->withConfirmedPassword()
            ->from(route('admin.payrolls.show', $payroll))
            ->post(route('admin.payrolls.pay', $payroll), ['payment_method' => PaymentMethod::Cash->value])
            ->assertSessionHasErrors('status');

        $this->assertSame(0, CashTransaction::query()->count());
    }

    public function test_only_the_owner_may_finalize_or_pay(): void
    {
        $manager = User::factory()->payrollManager()->create();
        $payroll = Payroll::factory()->create();

        $this->actingAs($manager)->withConfirmedPassword()->post(route('admin.payrolls.finalize', $payroll))->assertForbidden();
        $this->actingAs($manager)->withConfirmedPassword()
            ->post(route('admin.payrolls.pay', $payroll), ['payment_method' => PaymentMethod::Cash->value])
            ->assertForbidden();
    }

    public function test_an_employee_only_sees_their_own_payroll(): void
    {
        $mine = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();

        Payroll::factory()->create(['employee_id' => $mine->id, 'total' => 1234000]);
        Payroll::factory()->create(['employee_id' => $other->id, 'total' => 9999000]);

        $this->actingAs($mine)->withConfirmedPassword()
            ->get(route('admin.payrolls.index'))
            ->assertOk()
            ->assertSee('1.234.000')
            ->assertDontSee('9.999.000');
    }

    public function test_an_employee_cannot_open_someone_elses_payroll(): void
    {
        $mine = User::factory()->employee()->create();
        $other = Payroll::factory()->create();

        $this->actingAs($mine)->withConfirmedPassword()->get(route('admin.payrolls.show', $other))->assertForbidden();
    }

    public function test_recalculating_a_draft_picks_up_new_attendance(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = $this->employeeWithEarnings();

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.store'), $this->payload($employee));
        $payroll = Payroll::query()->firstOrFail();

        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-08-20',
            'shift_name' => 'Ca bù',
            'shift_value' => 2,
        ]);

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.recalculate', $payroll))->assertRedirect();

        $this->assertSame('4.50', $payroll->fresh()->shift_count);
        $this->assertSame('900000.00', $payroll->fresh()->shift_pay);
    }

    public function test_a_shift_on_the_last_day_of_the_period_is_counted(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->employee()->create(['base_salary' => 0, 'shift_rate' => 100000]);

        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-08-31',
            'shift_name' => 'Ca cuối kỳ',
            'shift_value' => 1,
        ]);

        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-08-01',
            'shift_name' => 'Ca đầu kỳ',
            'shift_value' => 1,
        ]);

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.payrolls.store'), $this->payload($employee));

        $payroll = Payroll::query()->firstOrFail();

        $this->assertSame('2.00', $payroll->shift_count);
        $this->assertSame('200000.00', $payroll->shift_pay);
    }
}
