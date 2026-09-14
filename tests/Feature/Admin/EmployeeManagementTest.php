<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\PayrollStatus;
use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Payroll;
use App\Models\User;
use Database\Factories\BranchFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Test dùng ngày cố định nên phải neo đồng hồ, nếu không chúng sẽ rơi
        // ra ngoài cửa sổ ghi lùi khi thời gian thật trôi qua.
        Carbon::setTestNow(Carbon::parse('2026-08-20 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Nguyễn Thị Mai',
            'email' => 'mai@caitiemneo.test',
            'role' => UserRole::Employee->value,
            'base_salary' => 3000000,
            'shift_rate' => 200000,
            'commission_rate' => 10,
        ], $extra);
    }

    public function test_the_owner_creates_an_account_with_a_password(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->withConfirmedPassword()
            ->post(route('admin.employees.store'), $this->payload([
                'branch_id' => BranchFactory::resolveId(),
                'password' => 'matkhau-rat-manh',
                'password_confirmation' => 'matkhau-rat-manh',
                'can_manage_appointments' => '1',
            ]))
            ->assertRedirect(route('admin.employees.index'));

        $employee = User::query()->where('email', 'mai@caitiemneo.test')->firstOrFail();

        $this->assertTrue($employee->can_manage_appointments);
        $this->assertFalse($employee->can_create_invoices);
        $this->assertTrue(Hash::check('matkhau-rat-manh', $employee->password));
    }

    /**
     * Access to the admin area is decided by the posting, not by the role, so a
     * new account is only usable if the two are created together.
     */
    public function test_creating_an_account_posts_it_to_the_chosen_branch(): void
    {
        $owner = User::factory()->owner()->create();
        $branch = Branch::factory()->create();

        $this->actingAs($owner)->withConfirmedPassword()
            ->post(route('admin.employees.store'), $this->payload([
                'branch_id' => $branch->id,
                'password' => 'matkhau-rat-manh',
                'password_confirmation' => 'matkhau-rat-manh',
            ]))
            ->assertRedirect(route('admin.employees.index'));

        $employee = User::query()->where('email', 'mai@caitiemneo.test')->firstOrFail();

        $this->assertDatabaseHas('employee_branch_assignments', [
            'user_id' => $employee->id,
            'branch_id' => $branch->id,
            'is_primary' => true,
            'starts_on' => '2026-08-20 00:00:00',
            'ends_on' => null,
            'created_by' => $owner->id,
        ]);

        $this->assertTrue($employee->canAccessBranch($branch, '2026-08-20'));
    }

    public function test_an_account_cannot_be_created_without_a_branch(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->withConfirmedPassword()
            ->from(route('admin.employees.create'))
            ->post(route('admin.employees.store'), $this->payload([
                'password' => 'matkhau-rat-manh',
                'password_confirmation' => 'matkhau-rat-manh',
            ]))
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseMissing('users', ['email' => 'mai@caitiemneo.test']);
    }

    /** Transfers belong to the assignment panel, which keeps the history. */
    public function test_the_edit_form_cannot_move_an_account_to_another_branch(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->employee()->create(['email' => 'giu@caitiemneo.test']);
        $otherBranch = Branch::factory()->create();

        $this->actingAs($owner)->withConfirmedPassword()
            ->from(route('admin.employees.edit', $employee))
            ->put(route('admin.employees.update', $employee), $this->payload([
                'email' => 'giu@caitiemneo.test',
                'branch_id' => $otherBranch->id,
                'is_active' => '1',
            ]))
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseMissing('employee_branch_assignments', [
            'user_id' => $employee->id,
            'branch_id' => $otherBranch->id,
        ]);
    }

    public function test_a_password_is_required_on_create_but_optional_on_edit(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->withConfirmedPassword()
            ->from(route('admin.employees.create'))
            ->post(route('admin.employees.store'), $this->payload(['branch_id' => BranchFactory::resolveId()]))
            ->assertSessionHasErrors('password');

        $employee = User::factory()->employee()->create(['email' => 'giu@caitiemneo.test']);
        $originalHash = $employee->password;

        $this->actingAs($owner)->withConfirmedPassword()
            ->put(route('admin.employees.update', $employee), $this->payload([
                'email' => 'giu@caitiemneo.test',
                'password' => '',
                'is_active' => '1',
            ]))
            ->assertRedirect(route('admin.employees.index'));

        $this->assertSame($originalHash, $employee->fresh()->password);
        $this->assertSame('Nguyễn Thị Mai', $employee->fresh()->name);
    }

    public function test_the_last_active_owner_cannot_be_deactivated(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->withConfirmedPassword()
            ->from(route('admin.employees.edit', $owner))
            ->put(route('admin.employees.update', $owner), $this->payload([
                'email' => $owner->email,
                'role' => UserRole::Owner->value,
            ]))
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($owner->fresh()->is_active);
    }

    public function test_an_owner_can_be_deactivated_when_another_one_remains(): void
    {
        $owner = User::factory()->owner()->create();
        $secondOwner = User::factory()->owner()->create();

        $this->actingAs($owner)->withConfirmedPassword()
            ->put(route('admin.employees.update', $secondOwner), $this->payload([
                'email' => $secondOwner->email,
                'role' => UserRole::Owner->value,
            ]))
            ->assertRedirect(route('admin.employees.index'));

        $this->assertFalse($secondOwner->fresh()->is_active);
    }

    public function test_a_manager_may_read_the_directory_but_not_change_accounts(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();

        $this->actingAs($manager)->withConfirmedPassword()->get(route('admin.employees.index'))->assertOk();
        $this->actingAs($manager)->withConfirmedPassword()->get(route('admin.employees.create'))->assertForbidden();
        $this->actingAs($manager)->withConfirmedPassword()->get(route('admin.employees.edit', $employee))->assertForbidden();
    }

    public function test_attendance_is_unique_per_employee_date_and_shift(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->employee()->create();

        $payload = [
            'employee_id' => $employee->id,
            'work_date' => '2026-08-10',
            'shift_name' => 'Ca sáng',
            'shift_value' => 1,
            'status' => AttendanceStatus::Present->value,
            'reason' => 'Nhân viên quên bấm vào ca',
        ];

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.attendance.store'), $payload);

        $this->actingAs($owner)->withConfirmedPassword()
            ->from(route('admin.attendance.index'))
            ->post(route('admin.attendance.store'), $payload)
            ->assertSessionHasErrors('shift_name');

        $this->assertSame(1, AttendanceRecord::query()->count());
    }

    public function test_check_out_must_be_after_check_in(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->employee()->create();

        $this->actingAs($owner)->withConfirmedPassword()
            ->from(route('admin.attendance.index'))
            ->post(route('admin.attendance.store'), [
                'employee_id' => $employee->id,
                'work_date' => '2026-08-10',
                'shift_name' => 'Ca sáng',
                'shift_value' => 1,
                'status' => AttendanceStatus::Present->value,
                'checked_in_at' => '2026-08-10 14:00:00',
                'checked_out_at' => '2026-08-10 09:00:00',
                'reason' => 'Sửa giờ theo báo cáo của quản lý ca',
            ])
            ->assertSessionHasErrors('checked_out_at');
    }

    public function test_a_shift_locked_by_a_finalized_payroll_cannot_be_deleted(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->employee()->create();

        $record = AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-08-10',
        ]);

        Payroll::factory()->create([
            'employee_id' => $employee->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => PayrollStatus::Finalized,
        ]);

        $this->actingAs($owner)->withConfirmedPassword()
            ->from(route('admin.attendance.index'))
            ->delete(route('admin.attendance.destroy', $record))
            ->assertSessionHasErrors('work_date');

        $this->assertSame(1, AttendanceRecord::query()->count());
    }
}
