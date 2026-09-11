<?php

namespace Tests\Feature\Attendance;

use App\Models\Branch;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchGpsConfigTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
    }

    public function test_a_new_branch_starts_with_gps_off_and_the_default_thresholds(): void
    {
        $this->actingAs($this->owner)->withConfirmedPassword()
            ->post(route('admin.branches.store'), [
                'code' => 'CN-NEW',
                'name' => 'Chi nhánh mới',
            ])
            ->assertRedirect();

        $branch = Branch::query()->where('code', 'CN-NEW')->firstOrFail();

        $this->assertFalse($branch->gps_attendance_enabled);
        $this->assertSame(100, $branch->attendance_radius_meters);
        $this->assertSame(150, $branch->attendance_accuracy_limit_meters);
        $this->assertFalse($branch->acceptsGpsAttendance());
    }

    public function test_the_owner_can_configure_coordinates_and_switch_gps_on(): void
    {
        $branch = Branch::factory()->create(['code' => 'CN-CFG']);

        $this->actingAs($this->owner)->withConfirmedPassword()
            ->put(route('admin.branches.update', $branch), [
                'code' => 'CN-CFG',
                'name' => $branch->name,
                'latitude' => '10.7769000',
                'longitude' => '106.7009000',
                'attendance_radius_meters' => 150,
                'attendance_accuracy_limit_meters' => 120,
                'gps_attendance_enabled' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $branch->refresh();

        $this->assertTrue($branch->gps_attendance_enabled);
        $this->assertSame(150, $branch->attendance_radius_meters);
        $this->assertSame(120, $branch->attendance_accuracy_limit_meters);
        $this->assertTrue($branch->acceptsGpsAttendance());
        // Stored as decimal so the value comes back exactly as entered.
        $this->assertSame('10.7769000', $branch->latitude);
        $this->assertSame('106.7009000', $branch->longitude);
    }

    public function test_gps_cannot_be_switched_on_without_coordinates(): void
    {
        $branch = Branch::factory()->create(['code' => 'CN-NOLL']);

        $this->actingAs($this->owner)->withConfirmedPassword()
            ->from(route('admin.branches.edit', $branch))
            ->put(route('admin.branches.update', $branch), [
                'code' => 'CN-NOLL',
                'name' => $branch->name,
                'attendance_radius_meters' => 100,
                'attendance_accuracy_limit_meters' => 150,
                'gps_attendance_enabled' => '1',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('latitude');

        $this->assertFalse($branch->fresh()->gps_attendance_enabled);
    }

    public function test_an_out_of_range_coordinate_is_refused(): void
    {
        $branch = Branch::factory()->create(['code' => 'CN-BAD']);

        $this->actingAs($this->owner)->withConfirmedPassword()
            ->from(route('admin.branches.edit', $branch))
            ->put(route('admin.branches.update', $branch), [
                'code' => 'CN-BAD',
                'name' => $branch->name,
                'latitude' => '95.0',
                'longitude' => '200.0',
                'attendance_radius_meters' => 100,
                'attendance_accuracy_limit_meters' => 150,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors(['latitude', 'longitude']);
    }

    public function test_a_radius_outside_the_sane_bounds_is_refused(): void
    {
        $branch = Branch::factory()->create(['code' => 'CN-RAD']);

        $this->actingAs($this->owner)->withConfirmedPassword()
            ->from(route('admin.branches.edit', $branch))
            ->put(route('admin.branches.update', $branch), [
                'code' => 'CN-RAD',
                'name' => $branch->name,
                'attendance_radius_meters' => 50000,
                'attendance_accuracy_limit_meters' => 150,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('attendance_radius_meters');
    }

    public function test_only_the_owner_may_manage_branch_gps(): void
    {
        $branch = Branch::factory()->create(['code' => 'CN-MGR']);
        $manager = User::factory()->manager()->withoutBranch()->atBranch($branch)->create();

        $this->actingAs($manager)->withConfirmedPassword()->get(route('admin.branches.edit', $branch))->assertForbidden();
    }

    public function test_a_manager_may_curate_the_shift_catalogue_of_their_own_branch(): void
    {
        $branch = Branch::factory()->create(['code' => 'CN-CAT']);
        $manager = User::factory()->manager()->withoutBranch()->atBranch($branch)->create();

        $this->actingAs($manager)->withConfirmedPassword()
            ->post(route('admin.work-shifts.store'), [
                'name' => 'Ca chiều',
                'branch_id' => $branch->id,
                'starts_at' => '11:00',
                'ends_at' => '21:00',
                'shift_value' => 1,
                'grace_minutes' => 5,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $shift = WorkShift::query()->where('name', 'Ca chiều')->firstOrFail();

        $this->assertSame($branch->id, $shift->branch_id);
        $this->assertSame(5, $shift->grace_minutes);
        $this->assertFalse($shift->crossesMidnight());
    }

    /** A shared template affects every shop, so it stays with the owner. */
    public function test_a_manager_cannot_create_a_company_wide_template(): void
    {
        $branch = Branch::factory()->create(['code' => 'CN-SHR']);
        $manager = User::factory()->manager()->withoutBranch()->atBranch($branch)->create();

        $this->actingAs($manager)->withConfirmedPassword()
            ->post(route('admin.work-shifts.store'), [
                'name' => 'Ca toàn hệ thống',
                'branch_id' => '',
                'starts_at' => '09:00',
                'ends_at' => '19:00',
                'shift_value' => 1,
                'grace_minutes' => 0,
                'is_active' => '1',
            ])
            ->assertRedirect();

        // The request pins it to a branch the manager actually runs rather
        // than letting an empty value widen its scope.
        $this->assertNotNull(WorkShift::query()->where('name', 'Ca toàn hệ thống')->value('branch_id'));
    }

    public function test_a_manager_cannot_edit_a_shared_template(): void
    {
        $branch = Branch::factory()->create(['code' => 'CN-SH2']);
        $manager = User::factory()->manager()->withoutBranch()->atBranch($branch)->create();
        $shared = WorkShift::factory()->shared()->create(['name' => 'Ca chung']);

        $this->actingAs($manager)->withConfirmedPassword()->get(route('admin.work-shifts.edit', $shared))->assertForbidden();
    }

    public function test_an_employee_cannot_reach_the_shift_catalogue(): void
    {
        $branch = Branch::factory()->create(['code' => 'CN-EMP']);
        $employee = User::factory()->employee()->withoutBranch()->atBranch($branch)->create();

        $this->actingAs($employee)->withConfirmedPassword()->get(route('admin.work-shifts.index'))->assertForbidden();
        $this->actingAs($employee)->withConfirmedPassword()->get(route('admin.work-shifts.create'))->assertForbidden();
    }

    public function test_a_duplicate_shift_name_within_a_branch_is_refused(): void
    {
        $branch = Branch::factory()->create(['code' => 'CN-DUP']);
        WorkShift::factory()->atBranch($branch)->create(['name' => 'Ca sáng']);

        $this->actingAs($this->owner)->withConfirmedPassword()
            ->from(route('admin.work-shifts.create'))
            ->post(route('admin.work-shifts.store'), [
                'name' => 'Ca sáng',
                'branch_id' => $branch->id,
                'starts_at' => '09:00',
                'ends_at' => '19:00',
                'shift_value' => 1,
                'grace_minutes' => 0,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_a_shift_ending_when_it_starts_is_refused(): void
    {
        $branch = Branch::factory()->create(['code' => 'CN-SAME']);

        $this->actingAs($this->owner)->withConfirmedPassword()
            ->from(route('admin.work-shifts.create'))
            ->post(route('admin.work-shifts.store'), [
                'name' => 'Ca lỗi',
                'branch_id' => $branch->id,
                'starts_at' => '09:00',
                'ends_at' => '09:00',
                'shift_value' => 1,
                'grace_minutes' => 0,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('ends_at');
    }
}
