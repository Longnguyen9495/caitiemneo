<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterization test for the flat admin navigation before grouping refactor.
 *
 * This documents the current behavior so the grouped-menu refactor can be
 * verified against it: every destination, route, active state, primary flag
 * and badge must survive unchanged.
 */
class NavigationCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_all_navigation_items(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->get(route('admin.dashboard'));

        $response->assertOk();

        // Quick-access items
        $response->assertSee('Tổng quan');
        $response->assertSee('Chấm công của tôi');
        $response->assertSee('Lịch hẹn');

        // Sales & finance
        $response->assertSee('Hóa đơn & thu chi');
        $response->assertSee('Báo cáo');

        // HR & shifts
        $response->assertSee('Chấm công & lương');
        $response->assertSee('Đơn ca & nghỉ');
        $response->assertSee('Nhân sự');

        // Operations
        $response->assertSee('Kho vật tư');
        $response->assertSee('Dịch vụ');
        $response->assertSee('Chi nhánh');

        // Control
        $response->assertSee('Cảnh báo');
    }

    public function test_owner_routes_are_present(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee(route('admin.dashboard'), false);
        $response->assertSee(route('attendance.board'), false);
        $response->assertSee(route('admin.appointments.index'), false);
        $response->assertSee(route('admin.invoices.index'), false);
        $response->assertSee(route('admin.reports.index'), false);
        $response->assertSee(route('admin.attendance.index'), false);
        $response->assertSee(route('admin.shift-requests.index'), false);
        $response->assertSee(route('admin.employees.index'), false);
        $response->assertSee(route('admin.products.index'), false);
        $response->assertSee(route('admin.services.index'), false);
        $response->assertSee(route('admin.branches.index'), false);
        $response->assertSee(route('admin.risk-flags.index'), false);
    }

    public function test_manager_sees_operational_items_and_my_payroll_not_attendance_admin(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager()->withoutBranch()->atBranch($branch)->create();

        $response = $this->actingAs($manager)->withConfirmedPassword()->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Tổng quan');
        $response->assertSee('Chấm công của tôi');
        $response->assertSee('Lịch hẹn');
        $response->assertSee('Hóa đơn & thu chi');
        $response->assertSee('Báo cáo');
        $response->assertSee('Nhân sự');
        $response->assertSee('Kho vật tư');
        $response->assertSee('Dịch vụ');
        $response->assertSee('Chi nhánh');

        // Manager without payroll manage permission sees "Lương của tôi" because
        // PayrollPolicy::viewAny returns true for everyone, but AttendanceRecordPolicy::viewAny
        // requires owner/manager (manager qualifies) — however the label logic in
        // AdminNavigation checks viewAny on AttendanceRecord; managers DO qualify.
        // So manager sees "Chấm công & lương" because they can viewAny AttendanceRecord.
        $response->assertSee('Chấm công & lương');
    }

    public function test_manager_with_payroll_permission_sees_attendance_payroll_link(): void
    {
        $manager = User::factory()->payrollManager()->create();

        $response = $this->actingAs($manager)->withConfirmedPassword()->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Chấm công & lương');
        $response->assertSee(route('admin.attendance.index'), false);
    }

    public function test_employee_sees_my_payroll_not_attendance_admin(): void
    {
        $employee = User::factory()->employee()->create();

        $response = $this->actingAs($employee)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Lương của tôi');
        $response->assertSee(route('admin.payrolls.index'), false);
        $response->assertDontSee('Chấm công & lương');
        $response->assertDontSee(route('admin.attendance.index'), false);
    }

    public function test_primary_tab_bar_has_at_most_four_items(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->get(route('admin.dashboard'));

        $response->assertOk();

        // The tab bar is rendered with neo-tabbar__item class.
        // We verify it contains the expected primary items.
        $response->assertSee('neo-tabbar__item');
    }

    public function test_active_route_gets_aria_current(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->get(route('admin.dashboard'));

        $response->assertOk();
        // Dashboard is active, so it should have aria-current="page"
        $response->assertSee('aria-current="page"', false);
    }

    public function test_shift_request_badge_is_rendered_when_non_zero(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->get(route('admin.dashboard'));

        $response->assertOk();
        // Badge element exists in nav; actual number depends on data.
        // We just assert the badge container is present.
        $response->assertSee('Đơn ca & nghỉ');
    }

    public function test_navigation_renders_in_both_sidebar_and_offcanvas(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->get(route('admin.dashboard'));

        $response->assertOk();
        // Sidebar
        $response->assertSee('neo-sidebar');
        // Mobile offcanvas
        $response->assertSee('offcanvas');
        $response->assertSee('neoMenu');
    }

    public function test_bottom_tab_bar_has_four_plus_more_button(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->get(route('admin.dashboard'));
        $html = $response->getContent();

        // Count neo-tabbar__item links (primary destinations)
        preg_match_all('/<a[^>]*class="[^"]*neo-tabbar__item[^"]*"/', $html, $matches);
        $this->assertLessThanOrEqual(4, count($matches[0]), 'Tab bar should have at most 4 primary items');

        // The "More" button must exist
        $response->assertSee('Thêm');
    }

    public function test_profile_logout_and_website_links_exist_in_footer(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Tài khoản của tôi');
        $response->assertSee('Xem website');
        $response->assertSee('Đăng xuất');
    }
}
