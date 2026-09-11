<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShiftScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $otherBranch;

    private User $manager;

    private User $employee;

    private WorkShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'CN-RA']);
        $this->otherBranch = Branch::factory()->create(['code' => 'CN-RB']);

        $this->manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();
        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();

        $this->shift = WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '19:00:00')
            ->create(['name' => 'Ca sáng']);
    }

    public function test_a_guest_cannot_reach_the_roster(): void
    {
        $this->get(route('admin.shift-schedule.index'))->assertRedirect(route('login'));
    }

    public function test_a_manager_can_roster_an_employee_and_the_planned_times_are_snapshotted(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.shift-schedule.store'), [
                'employee_id' => $this->employee->id,
                'work_shift_id' => $this->shift->id,
                'work_date' => '2026-09-14',
            ])
            ->assertRedirect();

        $assignment = ShiftAssignment::query()->firstOrFail();

        $this->assertSame($this->branch->id, $assignment->branch_id);
        $this->assertSame('Ca sáng', $assignment->shift_name);
        $this->assertSame('2026-09-14 09:00:00', $assignment->planned_start_at->toDateTimeString());
        $this->assertSame('2026-09-14 19:00:00', $assignment->planned_end_at->toDateTimeString());
        $this->assertSame($this->manager->id, $assignment->created_by);
    }

    /**
     * The whole reason for the snapshot: yesterday's roster must not move
     * when today's template is edited.
     */
    public function test_editing_the_template_does_not_change_an_existing_roster_entry(): void
    {
        $assignment = ShiftAssignment::factory()
            ->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($this->shift)->on('2026-09-14')->create();

        $this->actingAs($this->manager)
            ->put(route('admin.work-shifts.update', $this->shift), [
                'name' => 'Ca sáng',
                'branch_id' => $this->branch->id,
                'starts_at' => '11:00',
                'ends_at' => '21:00',
                'shift_value' => 1,
                'grace_minutes' => 0,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $assignment->refresh();

        $this->assertSame('2026-09-14 09:00:00', $assignment->planned_start_at->toDateTimeString());
        $this->assertSame('2026-09-14 19:00:00', $assignment->planned_end_at->toDateTimeString());
        $this->assertSame('11:00', $this->shift->fresh()->formatTime('starts_at'));
    }

    public function test_the_branch_is_taken_from_the_context_not_the_payload(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.shift-schedule.store'), [
                'employee_id' => $this->employee->id,
                'work_shift_id' => $this->shift->id,
                'work_date' => '2026-09-14',
                'branch_id' => $this->otherBranch->id,
            ])
            ->assertRedirect();

        $this->assertSame($this->branch->id, ShiftAssignment::query()->value('branch_id'));
    }

    public function test_an_employee_not_posted_to_the_branch_cannot_be_rostered(): void
    {
        $outsider = User::factory()->employee()->withoutBranch()->atBranch($this->otherBranch)->create();

        $this->actingAs($this->manager)
            ->from(route('admin.shift-schedule.index'))
            ->post(route('admin.shift-schedule.store'), [
                'employee_id' => $outsider->id,
                'work_shift_id' => $this->shift->id,
                'work_date' => '2026-09-14',
            ])
            ->assertSessionHasErrors('employee_id');

        $this->assertSame(0, ShiftAssignment::query()->count());
    }

    public function test_two_shifts_that_overlap_in_time_cannot_both_be_rostered(): void
    {
        $overlapping = WorkShift::factory()->atBranch($this->branch)->spanning('10:00:00', '20:00:00')
            ->create(['name' => 'Ca muộn']);

        ShiftAssignment::factory()
            ->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($this->shift)->on('2026-09-14')->create();

        $this->actingAs($this->manager)
            ->from(route('admin.shift-schedule.index'))
            ->post(route('admin.shift-schedule.store'), [
                'employee_id' => $this->employee->id,
                'work_shift_id' => $overlapping->id,
                'work_date' => '2026-09-14',
            ])
            ->assertSessionHasErrors('work_shift_id');

        $this->assertSame(1, ShiftAssignment::query()->count());
    }

    public function test_two_shifts_that_do_not_overlap_are_both_allowed(): void
    {
        $evening = WorkShift::factory()->atBranch($this->branch)->spanning('19:30:00', '22:00:00')
            ->create(['name' => 'Ca tối']);

        ShiftAssignment::factory()
            ->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($this->shift)->on('2026-09-14')->create();

        $this->actingAs($this->manager)
            ->post(route('admin.shift-schedule.store'), [
                'employee_id' => $this->employee->id,
                'work_shift_id' => $evening->id,
                'work_date' => '2026-09-14',
            ])
            ->assertRedirect();

        $this->assertSame(2, ShiftAssignment::query()->count());
    }

    public function test_a_template_of_another_branch_cannot_be_rostered(): void
    {
        $foreignShift = WorkShift::factory()->atBranch($this->otherBranch)->create(['name' => 'Ca cơ sở khác']);

        $this->actingAs($this->manager)
            ->from(route('admin.shift-schedule.index'))
            ->post(route('admin.shift-schedule.store'), [
                'employee_id' => $this->employee->id,
                'work_shift_id' => $foreignShift->id,
                'work_date' => '2026-09-14',
            ])
            ->assertSessionHasErrors('work_shift_id');
    }

    public function test_a_shared_template_can_be_rostered_at_any_branch(): void
    {
        $shared = WorkShift::factory()->shared()->spanning('08:00:00', '16:00:00')->create(['name' => 'Ca dùng chung']);

        $this->actingAs($this->manager)
            ->post(route('admin.shift-schedule.store'), [
                'employee_id' => $this->employee->id,
                'work_shift_id' => $shared->id,
                'work_date' => '2026-09-14',
            ])
            ->assertRedirect();

        $this->assertSame('Ca dùng chung', ShiftAssignment::query()->value('shift_name'));
    }

    public function test_an_inactive_template_cannot_be_rostered(): void
    {
        $retired = WorkShift::factory()->atBranch($this->branch)->inactive()->create(['name' => 'Ca cũ']);

        $this->actingAs($this->manager)
            ->from(route('admin.shift-schedule.index'))
            ->post(route('admin.shift-schedule.store'), [
                'employee_id' => $this->employee->id,
                'work_shift_id' => $retired->id,
                'work_date' => '2026-09-14',
            ])
            ->assertSessionHasErrors('work_shift_id');
    }

    public function test_an_employee_sees_only_their_own_row_in_the_weekly_grid(): void
    {
        $colleague = User::factory()->employee()->withoutBranch()->atBranch($this->branch)
            ->create(['name' => 'Đồng nghiệp trong lịch']);

        ShiftAssignment::factory()->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($this->shift)->on('2026-09-14')->create();
        ShiftAssignment::factory()->forEmployee($colleague)->atBranch($this->branch)
            ->usingShift($this->shift)->on('2026-09-14')->create();

        $this->actingAs($this->employee)
            ->get(route('admin.shift-schedule.index', ['week' => '2026-09-14']))
            ->assertOk()
            ->assertSee($this->employee->name)
            ->assertDontSee('Đồng nghiệp trong lịch');
    }

    public function test_an_employee_cannot_write_the_roster(): void
    {
        $this->actingAs($this->employee)
            ->post(route('admin.shift-schedule.store'), [
                'employee_id' => $this->employee->id,
                'work_shift_id' => $this->shift->id,
                'work_date' => '2026-09-14',
            ])
            ->assertForbidden();

        $this->assertSame(0, ShiftAssignment::query()->count());
    }

    public function test_a_manager_cannot_unroster_a_shift_of_another_branch(): void
    {
        $foreign = ShiftAssignment::factory()
            ->forEmployee(User::factory()->employee()->withoutBranch()->atBranch($this->otherBranch)->create())
            ->atBranch($this->otherBranch)
            ->usingShift(WorkShift::factory()->atBranch($this->otherBranch)->create())
            ->on('2026-09-14')
            ->create();

        $this->actingAs($this->manager)
            ->delete(route('admin.shift-schedule.destroy', $foreign))
            ->assertForbidden();
    }

    public function test_a_roster_entry_with_attendance_cannot_be_removed(): void
    {
        $assignment = ShiftAssignment::factory()
            ->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($this->shift)->on('2026-09-14')->create();

        AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'shift_assignment_id' => $assignment->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-14',
            'shift_name' => $assignment->shift_name,
        ]);

        $this->actingAs($this->manager)
            ->from(route('admin.shift-schedule.index'))
            ->delete(route('admin.shift-schedule.destroy', $assignment))
            ->assertSessionHasErrors('work_shift_id');

        $this->assertSame(1, ShiftAssignment::query()->count());
    }

    public function test_an_unworked_roster_entry_can_be_removed(): void
    {
        $assignment = ShiftAssignment::factory()
            ->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($this->shift)->on('2026-09-14')->create();

        $this->actingAs($this->manager)
            ->delete(route('admin.shift-schedule.destroy', $assignment))
            ->assertRedirect();

        $this->assertSame(0, ShiftAssignment::query()->count());
    }

    public function test_the_weekly_grid_shows_the_week_that_was_asked_for(): void
    {
        ShiftAssignment::factory()->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($this->shift)->on('2026-09-14')->create();

        $this->actingAs($this->manager)
            ->get(route('admin.shift-schedule.index', ['week' => '2026-09-16']))
            ->assertOk()
            ->assertSee('14/09')
            ->assertSee($this->employee->name);

        $this->actingAs($this->manager)
            ->get(route('admin.shift-schedule.index', ['week' => '2026-09-23']))
            ->assertOk()
            ->assertSee('Tuần này chưa phân ca cho ai');
    }

    public function test_an_overnight_template_is_snapshotted_across_midnight(): void
    {
        $night = WorkShift::factory()->atBranch($this->branch)->overnight()->create(['name' => 'Ca đêm']);

        $this->actingAs($this->manager)
            ->post(route('admin.shift-schedule.store'), [
                'employee_id' => $this->employee->id,
                'work_shift_id' => $night->id,
                'work_date' => '2026-09-14',
            ])
            ->assertRedirect();

        $assignment = ShiftAssignment::query()->firstOrFail();

        $this->assertSame('2026-09-14 21:00:00', $assignment->planned_start_at->toDateTimeString());
        $this->assertSame('2026-09-15 05:00:00', $assignment->planned_end_at->toDateTimeString());
        $this->assertTrue($assignment->crossesMidnight());
    }

    public function test_the_early_check_in_window_comes_from_the_shift_then_the_config(): void
    {
        $eager = WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '19:00:00')
            ->create(['name' => 'Ca mở sớm', 'early_check_in_minutes' => 90]);

        $withOverride = ShiftAssignment::factory()->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($eager)->on('2026-09-14')->create();

        $default = ShiftAssignment::factory()->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($this->shift)->on('2026-09-15')->create();

        $this->assertSame('2026-09-14 07:30:00', $withOverride->earliestCheckInAt()->toDateTimeString());
        $this->assertSame(
            Carbon::parse('2026-09-15 09:00:00')->subMinutes(config('attendance.early_check_in_minutes'))->toDateTimeString(),
            $default->earliestCheckInAt()->toDateTimeString(),
        );
    }
}
