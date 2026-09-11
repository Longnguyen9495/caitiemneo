<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\PayrollStatus;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Re-asking for the password before the most damaging actions.
 *
 * A live session is the whole prize: an unattended terminal or a stolen cookie
 * is enough to pay a payroll, reverse money or hand out permissions. Confirming
 * the password again costs a signed-in person two seconds and costs somebody
 * holding a borrowed session everything, because they do not have it.
 */
class StepUpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->owner = User::factory()->owner()->atBranch($this->branch)->create([
            'password' => Hash::make('password'),
        ]);
    }

    public function test_paying_a_payroll_asks_for_the_password_again(): void
    {
        $payroll = $this->finalisedPayroll();

        $this->actingAs($this->owner)
            ->post(route('admin.payrolls.pay', $payroll), ['payment_method' => 'cash'])
            ->assertRedirect(route('password.confirm'));

        $this->assertSame(PayrollStatus::Finalized, $payroll->fresh()->status);
    }

    public function test_paying_a_payroll_succeeds_once_the_password_is_confirmed(): void
    {
        $payroll = $this->finalisedPayroll();

        $this->actingAs($this->owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.payrolls.pay', $payroll), ['payment_method' => 'cash'])
            ->assertSessionHasNoErrors();

        $this->assertSame(PayrollStatus::Paid, $payroll->fresh()->status);
    }

    /** A confirmation from hours ago is not a confirmation. */
    public function test_a_stale_confirmation_is_refused(): void
    {
        $payroll = $this->finalisedPayroll();

        $this->actingAs($this->owner)
            ->withSession(['auth.password_confirmed_at' => time() - 60 * 60 * 5])
            ->post(route('admin.payrolls.pay', $payroll), ['payment_method' => 'cash'])
            ->assertRedirect(route('password.confirm'));
    }

    public function test_cancelling_a_payroll_asks_for_the_password_again(): void
    {
        $payroll = $this->finalisedPayroll();

        $this->actingAs($this->owner)
            ->delete(route('admin.payrolls.cancel', $payroll), ['reason' => 'Chot nham ky luong'])
            ->assertRedirect(route('password.confirm'));
    }

    public function test_changing_staff_permissions_asks_for_the_password_again(): void
    {
        $employee = User::factory()->employee()->atBranch($this->branch)->create();

        $this->actingAs($this->owner)
            ->put(route('admin.employees.update', $employee), [
                'name' => $employee->name,
                'email' => $employee->email,
                'role' => 'manager',
                'is_active' => 1,
            ])
            ->assertRedirect(route('password.confirm'));

        $this->assertSame('employee', $employee->fresh()->role->value);
    }

    /** Changing where the GPS fence sits changes whose hours count. */
    public function test_changing_branch_gps_asks_for_the_password_again(): void
    {
        $this->actingAs($this->owner)
            ->put(route('admin.branches.update', $this->branch), [
                'code' => $this->branch->code,
                'name' => $this->branch->name,
                'is_active' => 1,
                'latitude' => 10.77,
                'longitude' => 106.7,
                'gps_radius_meters' => 150,
            ])
            ->assertRedirect(route('password.confirm'));
    }

    public function test_exporting_payroll_asks_for_the_password_again(): void
    {
        $this->actingAs($this->owner)
            ->withSession([BranchContext::SESSION_KEY => $this->branch->getKey()])
            ->get(route('admin.reports.export.payrolls'))
            ->assertRedirect(route('password.confirm'));
    }

    /** Reversing money already collected is the point; a draft is not. */
    public function test_cancelling_a_paid_invoice_asks_for_the_password_again(): void
    {
        $invoice = Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->owner->getKey(),
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->delete(route('admin.invoices.cancel', $invoice), ['cancel_reason' => 'Khach doi y'])
            ->assertRedirect(route('password.confirm'));

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_cancelling_a_draft_invoice_is_not_interrupted(): void
    {
        $invoice = Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->owner->getKey(),
            'status' => InvoiceStatus::Draft,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('admin.invoices.cancel', $invoice), ['cancel_reason' => 'Khach doi y'])
            ->assertSessionHasNoErrors();

        $this->assertSame(InvoiceStatus::Cancelled, $invoice->fresh()->status);
    }

    /** Everyday work must not be interrupted by a password prompt. */
    public function test_ordinary_screens_do_not_ask_for_the_password(): void
    {
        foreach (['admin.invoices.index', 'admin.cash.index', 'admin.payrolls.index', 'admin.reports.index'] as $route) {
            $this->actingAs($this->owner)
                ->get(route($route))
                ->assertOk();
        }
    }

    /**
     * Deactivating somebody must end their sessions there and then.
     *
     * Otherwise the dismissal only takes effect when the person happens to log
     * out, which for a dismissal is precisely the wrong time to wait for.
     */
    public function test_deactivating_an_account_ends_its_other_sessions(): void
    {
        $employee = User::factory()->employee()->atBranch($this->branch)->create();
        $this->seedSessionFor($employee);

        $this->actingAs($this->owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('admin.employees.update', $employee), [
                'name' => $employee->name,
                'email' => $employee->email,
                'role' => $employee->role->value,
                'is_active' => 0,
            ] + $this->salaryFields())
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->sessionCountFor($employee));
    }

    public function test_changing_a_role_ends_the_accounts_other_sessions(): void
    {
        $employee = User::factory()->employee()->atBranch($this->branch)->create();
        $this->seedSessionFor($employee);

        $this->actingAs($this->owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('admin.employees.update', $employee), [
                'name' => $employee->name,
                'email' => $employee->email,
                'role' => 'manager',
                'is_active' => 1,
            ] + $this->salaryFields())
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->sessionCountFor($employee));
    }

    /** An edit that changes nothing sensitive must not log anybody out. */
    public function test_an_ordinary_edit_leaves_sessions_alone(): void
    {
        $employee = User::factory()->employee()->atBranch($this->branch)->create();
        $this->seedSessionFor($employee);

        $this->actingAs($this->owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('admin.employees.update', $employee), [
                'name' => 'Ten moi',
                'email' => $employee->email,
                'role' => $employee->role->value,
                'is_active' => 1,
            ] + $this->salaryFields())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->sessionCountFor($employee));
    }

    /** @return array<string, mixed> */
    private function salaryFields(): array
    {
        return ['base_salary' => 0, 'shift_rate' => 0, 'commission_rate' => 0];
    }

    /**
     * Phiên đang mở của một tài khoản khác.
     *
     * Suite chạy với session driver `array` cho nhanh, nhưng production dùng
     * `database`; việc thu hồi chỉ có ý nghĩa với driver database nên test
     * chuyển sang đúng driver đó thay vì kiểm tra một nhánh không dùng thật.
     */
    private function seedSessionFor(User $user): void
    {
        config(['session.driver' => 'database']);

        DB::table('sessions')->insert([
            'id' => 'phien-cu-'.$user->getKey(),
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => time(),
        ]);
    }

    private function sessionCountFor(User $user): int
    {
        return DB::table('sessions')->where('user_id', $user->getKey())->count();
    }

    private function finalisedPayroll(): Payroll
    {
        return Payroll::factory()->create([
            'employee_id' => User::factory()->employee()->atBranch($this->branch)->create()->getKey(),
            'paying_branch_id' => $this->branch->getKey(),
            'status' => PayrollStatus::Finalized,
            'final_total' => '5000000.00',
        ]);
    }
}
