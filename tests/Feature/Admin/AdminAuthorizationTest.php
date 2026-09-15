<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function adminRoutes(): array
    {
        return [
            'invoices' => ['admin.invoices.index'],
            'cash book' => ['admin.cash.index'],
            'products' => ['admin.products.index'],
            'suppliers' => ['admin.suppliers.index'],
            'inventory' => ['admin.inventory.index'],
            'employees' => ['admin.employees.index'],
            'attendance' => ['admin.attendance.index'],
            'payrolls' => ['admin.payrolls.index'],
            'reports' => ['admin.reports.index'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_guests_are_redirected_to_login(string $routeName): void
    {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    #[DataProvider('adminRoutes')]
    public function test_owner_can_reach_every_module(string $routeName): void
    {
        $this->actingAs(User::factory()->owner()->create())->withConfirmedPassword()
            ->get(route($routeName))
            ->assertOk();
    }

    public function test_manager_reaches_operational_modules_but_not_reserved_ones(): void
    {
        $manager = User::factory()->manager()->create();

        foreach (['admin.invoices.index', 'admin.cash.index', 'admin.products.index', 'admin.inventory.index', 'admin.employees.index', 'admin.reports.index'] as $routeName) {
            $this->actingAs($manager)->withConfirmedPassword()->get(route($routeName))->assertOk();
        }

        $this->actingAs($manager)->withConfirmedPassword()->get(route('admin.payrolls.create'))->assertForbidden();
        $this->actingAs($manager)->withConfirmedPassword()->get(route('admin.reports.export.payrolls'))->assertForbidden();
    }

    public function test_manager_with_explicit_grant_may_manage_payroll(): void
    {
        $this->actingAs(User::factory()->payrollManager()->create())->withConfirmedPassword()
            ->get(route('admin.payrolls.create'))
            ->assertOk();
    }

    public function test_employee_navigation_links_payroll_instead_of_restricted_attendance_admin_page(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Lương của tôi')
            ->assertSee('href="'.route('admin.payrolls.index').'"', false)
            ->assertDontSee('href="'.route('admin.attendance.index').'"', false);
    }

    public function test_employee_is_limited_to_their_operational_areas(): void
    {
        $employee = User::factory()->employee()->create([
            'can_manage_appointments' => true,
            'can_create_invoices' => false,
        ]);

        $this->actingAs($employee)->withConfirmedPassword()->get(route('admin.appointments.index'))->assertOk();
        // Nhân viên được mở danh sách để xem hóa đơn của lịch mình care;
        // InvoiceQuery tiếp tục giới hạn các dòng nhìn thấy theo phân công.
        $this->actingAs($employee)->withConfirmedPassword()->get(route('admin.invoices.index'))->assertOk();
        $this->actingAs($employee)->withConfirmedPassword()->get(route('admin.cash.index'))->assertForbidden();
        $this->actingAs($employee)->withConfirmedPassword()->get(route('admin.employees.index'))->assertForbidden();
        $this->actingAs($employee)->withConfirmedPassword()->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($employee)->withConfirmedPassword()->get(route('admin.services.index'))->assertForbidden();
    }

    public function test_employee_may_read_inventory_but_not_change_it(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)->withConfirmedPassword()->get(route('admin.products.index'))->assertOk();
        $this->actingAs($employee)->withConfirmedPassword()->get(route('admin.inventory.index'))->assertOk();
        $this->actingAs($employee)->withConfirmedPassword()->get(route('admin.inventory.create'))->assertForbidden();
        $this->actingAs($employee)->withConfirmedPassword()->get(route('admin.products.create'))->assertForbidden();
        $this->actingAs($employee)->withConfirmedPassword()->get(route('admin.suppliers.index'))->assertForbidden();
    }

    public function test_employee_with_invoice_permission_reaches_invoices_only(): void
    {
        $employee = User::factory()->employee()->create(['can_create_invoices' => true]);

        $this->actingAs($employee)->withConfirmedPassword()->get(route('admin.invoices.index'))->assertOk();
        $this->actingAs($employee)->withConfirmedPassword()->get(route('admin.cash.index'))->assertForbidden();
    }

    public function test_inactive_user_cannot_enter_the_admin_area(): void
    {
        $this->actingAs(User::factory()->owner()->inactive()->create())->withConfirmedPassword()
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
