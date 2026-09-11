<?php

namespace Tests\Feature\Attendance;

use App\Enums\OvertimeStatus;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every new screen is opened at least once with real data in it.
 *
 * A Blade typo in a partial only shows up when the branch it lives in is
 * actually reached, so these walk the pages with the rows that trigger the
 * conditional sections.
 */
class AttendancePagesRenderTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $owner;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'code' => 'CN-VIEW',
            'latitude' => 10.7769000,
            'longitude' => 106.7009000,
            'gps_attendance_enabled' => true,
        ]);

        $this->owner = User::factory()->owner()->create();
        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
    }

    public function test_the_shift_catalogue_pages_render(): void
    {
        WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '19:00:00')->create(['name' => 'Ca sáng']);
        WorkShift::factory()->shared()->overnight()->create(['name' => 'Ca đêm chung']);

        $this->actingAs($this->owner)
            ->get(route('admin.work-shifts.index', ['branch' => $this->branch->id]))
            ->assertOk()
            ->assertSee('Ca sáng')
            ->assertSee('Ca đêm chung')
            ->assertSee('Dùng chung mọi chi nhánh')
            ->assertSee('(+1 ngày)');

        $this->actingAs($this->owner)->get(route('admin.work-shifts.create'))->assertOk();
    }

    public function test_the_shift_catalogue_edit_form_renders_with_the_stored_times(): void
    {
        $shift = WorkShift::factory()->atBranch($this->branch)->spanning('11:00:00', '21:00:00')
            ->create(['name' => 'Ca chiều', 'grace_minutes' => 10]);

        $this->actingAs($this->owner)
            ->get(route('admin.work-shifts.edit', $shift))
            ->assertOk()
            ->assertSee('value="11:00"', false)
            ->assertSee('value="21:00"', false);
    }

    public function test_the_branch_form_renders_the_gps_section(): void
    {
        $this->actingAs($this->owner)
            ->get(route('admin.branches.edit', $this->branch))
            ->assertOk()
            ->assertSee('Chấm công GPS')
            ->assertSee('Bán kính cho phép (m)')
            ->assertSee('10.7769000');
    }

    public function test_the_attendance_edit_form_renders_the_evidence_and_the_audit_trail(): void
    {
        $record = $this->recordWithOvertime();

        $this->actingAs($this->owner)
            ->get(route('admin.attendance.edit', $record))
            ->assertOk()
            ->assertSee('Dữ liệu hệ thống ghi nhận')
            ->assertSee('Lý do chỉnh sửa')
            ->assertSee('Duyệt tăng ca');
    }

    public function test_the_attendance_index_shows_the_pending_overtime_shortcut(): void
    {
        $this->recordWithOvertime();

        $this->actingAs($this->owner)
            ->get(route('admin.attendance.index', ['month' => '2026-09', 'branch' => $this->branch->id]))
            ->assertOk()
            ->assertSee('Tăng ca chờ duyệt')
            ->assertSee('45p tăng ca');
    }

    public function test_the_review_queue_renders_every_section(): void
    {
        $record = $this->recordWithOvertime();

        // An open shift from a past day, which is the second section.
        AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-10',
            'shift_name' => 'Ca bỏ quên',
            'checked_in_at' => '2026-09-10 09:00:00',
            'checked_out_at' => null,
        ]);

        $record->auditLogs()->create([
            'branch_id' => $this->branch->id,
            'actor_id' => $this->owner->id,
            'action' => 'manual_update',
            'reason' => 'Sửa theo báo cáo của quản lý ca',
            'before' => ['status' => 'present'],
            'after' => ['status' => 'late'],
        ]);

        $this->actingAs($this->owner)
            ->get(route('admin.attendance.review', ['branch' => $this->branch->id]))
            ->assertOk()
            ->assertSee('Tăng ca chờ duyệt')
            ->assertSee('Ca chưa có giờ ra')
            ->assertSee('Ca bỏ quên')
            ->assertSee('Nhật ký chỉnh sửa')
            ->assertSee('Sửa theo báo cáo của quản lý ca');
    }

    public function test_the_employee_board_renders_with_no_shift_at_all(): void
    {
        $this->actingAs($this->employee)
            ->get(route('attendance.board'))
            ->assertOk()
            ->assertSee('Hiện chưa có ca nào để chấm công')
            ->assertSee('Chưa có lịch sử chấm công');
    }

    public function test_the_employee_board_renders_an_open_session(): void
    {
        $shift = WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '19:00:00')->create();

        $assignment = ShiftAssignment::factory()
            ->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($shift)->on(now()->toDateString())->create();

        AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'shift_assignment_id' => $assignment->id,
            'employee_id' => $this->employee->id,
            'work_date' => now()->toDateString(),
            'shift_name' => $assignment->shift_name,
            'checked_in_at' => now()->copy()->subHour(),
            'checked_out_at' => null,
        ]);

        $this->actingAs($this->employee)
            ->get(route('attendance.board'))
            ->assertOk()
            ->assertSee('Đang làm ca')
            ->assertSee('Ra ca');
    }

    public function test_the_weekly_roster_renders_for_a_manager(): void
    {
        $shift = WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '19:00:00')->create(['name' => 'Ca sáng']);
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();

        ShiftAssignment::factory()->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($shift)->on('2026-09-14')->create();

        $this->actingAs($manager)
            ->get(route('admin.shift-schedule.index', ['week' => '2026-09-14']))
            ->assertOk()
            ->assertSee('Phân ca nhanh')
            ->assertSee('09:00–19:00')
            ->assertSee($this->employee->name);
    }

    private function recordWithOvertime(): AttendanceRecord
    {
        $shift = WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '19:00:00')->create();

        $assignment = ShiftAssignment::factory()
            ->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($shift)->on('2026-09-14')->create();

        $record = AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'shift_assignment_id' => $assignment->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-14',
            'shift_name' => $assignment->shift_name,
            'checked_in_at' => '2026-09-14 09:00:00',
            'checked_out_at' => '2026-09-14 19:45:00',
        ]);

        $record->forceFill([
            'source' => 'gps',
            'check_in_verification' => 'verified',
            'check_in_distance_meters' => 42,
            'check_in_accuracy_meters' => 12,
            'check_out_verification' => 'verified',
            'check_out_distance_meters' => 38,
            'check_out_accuracy_meters' => 14,
            'overtime_minutes' => 45,
            'overtime_status' => OvertimeStatus::Pending,
        ])->save();

        return $record;
    }
}
