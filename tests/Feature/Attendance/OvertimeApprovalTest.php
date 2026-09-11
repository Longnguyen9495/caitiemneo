<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\OvertimeStatus;
use App\Enums\PayrollStatus;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Payroll;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimeApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $otherBranch;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'CN-OT1']);
        $this->otherBranch = Branch::factory()->create(['code' => 'CN-OT2']);
        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
    }

    public function test_an_employee_cannot_approve_their_own_overtime(): void
    {
        $record = $this->recordWithOvertime(90);

        $this->actingAs($this->employee)
            ->patch(route('admin.attendance.overtime', $record), [
                'overtime_status' => OvertimeStatus::Approved->value,
                'approved_overtime_minutes' => 90,
            ])
            ->assertForbidden();

        $this->assertSame(OvertimeStatus::Pending, $record->fresh()->overtime_status);
    }

    public function test_a_manager_of_another_branch_cannot_approve(): void
    {
        $record = $this->recordWithOvertime(90);
        $outsider = User::factory()->manager()->withoutBranch()->atBranch($this->otherBranch)->create();

        $this->actingAs($outsider)
            ->patch(route('admin.attendance.overtime', $record), [
                'overtime_status' => OvertimeStatus::Approved->value,
                'approved_overtime_minutes' => 90,
            ])
            ->assertForbidden();

        $this->assertSame(OvertimeStatus::Pending, $record->fresh()->overtime_status);
    }

    public function test_a_manager_of_the_same_branch_may_approve(): void
    {
        $record = $this->recordWithOvertime(90);
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();

        $this->actingAs($manager)
            ->patch(route('admin.attendance.overtime', $record), [
                'overtime_status' => OvertimeStatus::Approved->value,
                'approved_overtime_minutes' => 90,
                'overtime_approval_note' => 'Khách kéo dài tới muộn',
            ])
            ->assertRedirect();

        $record->refresh();

        $this->assertSame(OvertimeStatus::Approved, $record->overtime_status);
        $this->assertSame(90, $record->approved_overtime_minutes);
        $this->assertSame(90, $record->payableOvertimeMinutes());
        $this->assertSame($manager->id, $record->overtime_approved_by);
        $this->assertNotNull($record->overtime_approved_at);
        $this->assertSame('Khách kéo dài tới muộn', $record->overtime_approval_note);
    }

    public function test_the_owner_may_approve_at_any_branch(): void
    {
        $record = $this->recordWithOvertime(45);
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('admin.attendance.overtime', $record), [
                'overtime_status' => OvertimeStatus::Approved->value,
                'approved_overtime_minutes' => 45,
            ])
            ->assertRedirect();

        $this->assertSame(OvertimeStatus::Approved, $record->fresh()->overtime_status);
    }

    public function test_a_reviewer_may_trim_the_approved_minutes(): void
    {
        $record = $this->recordWithOvertime(90);
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();

        $this->actingAs($manager)
            ->patch(route('admin.attendance.overtime', $record), [
                'overtime_status' => OvertimeStatus::Approved->value,
                'approved_overtime_minutes' => 60,
                'overtime_approval_note' => 'Trừ 30 phút nghỉ ăn tối',
            ])
            ->assertRedirect();

        $record->refresh();

        $this->assertSame(90, $record->overtime_minutes);
        $this->assertSame(60, $record->approved_overtime_minutes);
    }

    /** Paying for time the clock never saw would need its own kind of entry. */
    public function test_a_reviewer_cannot_approve_more_minutes_than_were_detected(): void
    {
        $record = $this->recordWithOvertime(30);
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();

        $this->actingAs($manager)
            ->from(route('admin.attendance.review'))
            ->patch(route('admin.attendance.overtime', $record), [
                'overtime_status' => OvertimeStatus::Approved->value,
                'approved_overtime_minutes' => 300,
            ])
            ->assertSessionHasErrors('approved_overtime_minutes');

        $this->assertSame(OvertimeStatus::Pending, $record->fresh()->overtime_status);
    }

    public function test_rejecting_leaves_no_approved_minutes(): void
    {
        $record = $this->recordWithOvertime(90);
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();

        $this->actingAs($manager)
            ->patch(route('admin.attendance.overtime', $record), [
                'overtime_status' => OvertimeStatus::Rejected->value,
                'approved_overtime_minutes' => 90,
                'overtime_approval_note' => 'Không có yêu cầu ở lại thêm',
            ])
            ->assertRedirect();

        $record->refresh();

        $this->assertSame(OvertimeStatus::Rejected, $record->overtime_status);
        $this->assertSame(0, $record->approved_overtime_minutes);
        $this->assertSame(0, $record->payableOvertimeMinutes());
    }

    public function test_a_decision_is_written_to_the_audit_trail(): void
    {
        $record = $this->recordWithOvertime(90);
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();

        $this->actingAs($manager)->patch(route('admin.attendance.overtime', $record), [
            'overtime_status' => OvertimeStatus::Approved->value,
            'approved_overtime_minutes' => 75,
            'overtime_approval_note' => 'Duyệt 75 phút',
        ]);

        $log = $record->auditLogs()->firstOrFail();

        $this->assertSame('overtime_approved', $log->action->value);
        $this->assertSame($manager->id, $log->actor_id);
        $this->assertSame('Duyệt 75 phút', $log->reason);
        $this->assertSame(0, $log->before['approved_overtime_minutes']);
        $this->assertSame(75, $log->after['approved_overtime_minutes']);
    }

    /** Approving minutes moves money, so a closed payroll blocks it. */
    public function test_a_closed_payroll_blocks_the_overtime_decision(): void
    {
        $record = $this->recordWithOvertime(90);
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();

        Payroll::factory()->create([
            'employee_id' => $this->employee->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'status' => PayrollStatus::Finalized,
        ]);

        $this->actingAs($manager)
            ->from(route('admin.attendance.review'))
            ->patch(route('admin.attendance.overtime', $record), [
                'overtime_status' => OvertimeStatus::Approved->value,
                'approved_overtime_minutes' => 90,
            ])
            ->assertSessionHasErrors('overtime_status');

        $this->assertSame(OvertimeStatus::Pending, $record->fresh()->overtime_status);
    }

    public function test_a_shift_with_no_overrun_has_nothing_to_decide(): void
    {
        $record = $this->recordWithOvertime(0);
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();

        $this->actingAs($manager)
            ->from(route('admin.attendance.review'))
            ->patch(route('admin.attendance.overtime', $record), [
                'overtime_status' => OvertimeStatus::Approved->value,
                'approved_overtime_minutes' => 0,
            ])
            ->assertSessionHasErrors('overtime_status');
    }

    public function test_the_review_queue_only_lists_the_active_branch(): void
    {
        $mine = $this->recordWithOvertime(90);
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();

        $theirs = User::factory()->employee()->withoutBranch()->atBranch($this->otherBranch)
            ->create(['name' => 'Nhân viên cơ sở khác']);

        AttendanceRecord::factory()->create([
            'branch_id' => $this->otherBranch->id,
            'employee_id' => $theirs->id,
            'work_date' => '2026-09-14',
            'shift_name' => 'Ca chính',
            'overtime_minutes' => 60,
            'overtime_status' => OvertimeStatus::Pending,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.attendance.review'))
            ->assertOk()
            ->assertSee($mine->employee->name)
            ->assertDontSee('Nhân viên cơ sở khác');
    }

    public function test_an_employee_cannot_open_the_review_queue(): void
    {
        $this->actingAs($this->employee)->get(route('admin.attendance.review'))->assertForbidden();
    }

    private function recordWithOvertime(int $minutes): AttendanceRecord
    {
        $shift = WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '19:00:00')->create();

        $assignment = ShiftAssignment::factory()
            ->forEmployee($this->employee)
            ->atBranch($this->branch)
            ->usingShift($shift)
            ->on('2026-09-14')
            ->create();

        return AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'shift_assignment_id' => $assignment->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-14',
            'shift_name' => $assignment->shift_name,
            'status' => AttendanceStatus::Present,
            'checked_in_at' => '2026-09-14 09:00:00',
            'checked_out_at' => $assignment->planned_end_at->copy()->addMinutes($minutes),
            'overtime_minutes' => $minutes,
            'overtime_status' => $minutes > 0 ? OvertimeStatus::Pending : OvertimeStatus::None,
        ]);
    }
}
