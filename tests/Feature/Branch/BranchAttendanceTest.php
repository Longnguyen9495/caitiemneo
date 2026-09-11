<?php

namespace Tests\Feature\Branch;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::factory()->create(['code' => 'CN-TA']);
        $this->branchB = Branch::factory()->create(['code' => 'CN-TB']);
    }

    public function test_a_shift_is_recorded_against_the_active_branch(): void
    {
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branchA)->create();
        $employee = User::factory()->employee()->withoutBranch()->atBranch($this->branchA)->create();

        $this->actingAs($manager)->post(route('admin.attendance.store'), [
            'employee_id' => $employee->id,
            'work_date' => '2026-08-10',
            'shift_name' => 'Ca sáng',
            'shift_value' => 1,
            'status' => AttendanceStatus::Present->value,
            'reason' => 'Ghi bù ca cho nhân viên quên chấm công',
        ])->assertRedirect();

        $this->assertSame($this->branchA->id, AttendanceRecord::query()->value('branch_id'));
    }

    public function test_the_attendance_list_is_limited_to_the_active_branch(): void
    {
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branchA)->create();
        $mine = User::factory()->employee()->withoutBranch()->atBranch($this->branchA)->create(['name' => 'Nhân viên cơ sở A']);
        $theirs = User::factory()->employee()->withoutBranch()->atBranch($this->branchB)->create(['name' => 'Nhân viên cơ sở B']);

        AttendanceRecord::factory()->create(['branch_id' => $this->branchA->id, 'employee_id' => $mine->id, 'work_date' => '2026-08-10', 'shift_name' => 'A']);
        AttendanceRecord::factory()->create(['branch_id' => $this->branchB->id, 'employee_id' => $theirs->id, 'work_date' => '2026-08-10', 'shift_name' => 'B']);

        $this->actingAs($manager)
            ->get(route('admin.attendance.index', ['month' => '2026-08']))
            ->assertOk()
            ->assertSee('Nhân viên cơ sở A')
            ->assertDontSee('Nhân viên cơ sở B');
    }

    public function test_a_manager_cannot_edit_a_shift_of_another_branch(): void
    {
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branchA)->create();
        $foreign = AttendanceRecord::factory()->create(['branch_id' => $this->branchB->id, 'work_date' => '2026-08-10']);

        $this->actingAs($manager)->get(route('admin.attendance.edit', $foreign))->assertForbidden();
        $this->actingAs($manager)->delete(route('admin.attendance.destroy', $foreign))->assertForbidden();
    }

    public function test_an_overlapping_posting_at_the_same_branch_is_refused(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->employee()->withoutBranch()->atBranch($this->branchA, true, '2026-01-01', '2026-12-31')->create();

        $this->actingAs($owner)
            ->from(route('admin.employees.edit', $employee))
            ->post(route('admin.employees.assignments.store', $employee), [
                'branch_id' => $this->branchA->id,
                'starts_on' => '2026-06-01',
                'is_primary' => '1',
            ])
            ->assertSessionHasErrors('starts_on');
    }

    public function test_a_posting_can_be_ended_but_not_deleted(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->employee()->withoutBranch()->atBranch($this->branchA)->create();
        $assignment = $employee->branchAssignments()->firstOrFail();

        $this->actingAs($owner)
            ->patch(route('admin.employees.assignments.update', [$employee, $assignment]), ['ends_on' => '2026-09-30'])
            ->assertRedirect();

        $this->assertSame('2026-09-30', $assignment->fresh()->ends_on->toDateString());
        $this->assertSame(1, $employee->branchAssignments()->count());
    }

    public function test_only_the_owner_may_post_staff_to_a_branch(): void
    {
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branchA)->create();
        $employee = User::factory()->employee()->withoutBranch()->atBranch($this->branchA)->create();

        $this->actingAs($manager)
            ->post(route('admin.employees.assignments.store', $employee), [
                'branch_id' => $this->branchB->id,
                'starts_on' => '2026-09-01',
            ])
            ->assertForbidden();
    }
}
