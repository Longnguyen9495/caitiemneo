<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-3: Lịch chấm công cá nhân trên màn hình nhân viên.
 *
 * - Render đúng tháng.
 * - Chỉ thấy dữ liệu chính mình.
 * - Hiện assignment tương lai.
 * - Nhiều ca cùng ngày.
 * - Không ảnh hưởng check-in/check-out GPS.
 */
class PersonalCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $employee;

    private User $otherEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'code' => 'CN-CAL',
            'latitude' => 10.7769000,
            'longitude' => 106.7009000,
            'gps_attendance_enabled' => true,
        ]);

        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
        $this->otherEmployee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
    }

    public function test_the_board_renders_the_calendar_grid(): void
    {
        Carbon::setTestNow('2026-09-16');

        $response = $this->actingAs($this->employee)
            ->get(route('attendance.board'));

        $response->assertOk()
            ->assertSee('Lịch chấm công tháng');

        $html = $response->getContent();
        // Title uses Carbon isoFormat with Vietnamese locale; accept either format
        $this->assertTrue(
            str_contains($html, 'Tháng 9') || str_contains($html, 'tháng 9'),
            'Expected Vietnamese month title in response.'
        );
    }

    public function test_the_calendar_shows_own_record_but_not_anothers(): void
    {
        Carbon::setTestNow('2026-09-16');
        $shift = WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '19:00:00')->create();
        $otherShift = WorkShift::factory()->atBranch($this->branch)->spanning('14:00:00', '22:00:00')->create(['name' => 'Ca chiều riêng']);

        $myAssignment = ShiftAssignment::factory()
            ->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($shift)->on('2026-09-14')->create();

        $otherAssignment = ShiftAssignment::factory()
            ->forEmployee($this->otherEmployee)->atBranch($this->branch)
            ->usingShift($otherShift)->on('2026-09-14')->create();

        AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'shift_assignment_id' => $myAssignment->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-14',
            'shift_name' => $myAssignment->shift_name,
            'checked_in_at' => '2026-09-14 09:00:00',
            'checked_out_at' => '2026-09-14 19:00:00',
            'status' => 'present',
        ]);

        AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'shift_assignment_id' => $otherAssignment->id,
            'employee_id' => $this->otherEmployee->id,
            'work_date' => '2026-09-14',
            'shift_name' => $otherAssignment->shift_name,
            'checked_in_at' => '2026-09-14 09:05:00',
            'checked_out_at' => '2026-09-14 19:05:00',
            'status' => 'late',
        ]);

        $response = $this->actingAs($this->employee)
            ->get(route('attendance.board'));

        $response->assertOk();
        $html = $response->getContent();

        // The cell should have tone strip (has-data) for the employee's own record
        $this->assertStringContainsString('has-data', $html);
        $this->assertStringContainsString('neo-cal__tone--success', $html);

        // But should NOT expose the other employee's shift name
        $this->assertStringNotContainsString('Ca chiều riêng', $html);
    }

    public function test_the_calendar_shows_future_assignments(): void
    {
        Carbon::setTestNow('2026-09-16');
        $shift = WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '19:00:00')->create();

        ShiftAssignment::factory()
            ->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($shift)->on('2026-09-20')->create();

        $response = $this->actingAs($this->employee)
            ->get(route('attendance.board'));

        $response->assertOk();
        $html = $response->getContent();

        // Future assignment without a record is still shown as planned
        $this->assertStringContainsString('data-cal-date="2026-09-20"', $html);
    }

    public function test_multiple_assignments_on_the_same_day_show_count_badge(): void
    {
        Carbon::setTestNow('2026-09-16');
        $shiftA = WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '14:00:00')->create();
        $shiftB = WorkShift::factory()->atBranch($this->branch)->spanning('14:00:00', '20:00:00')->create();

        $assignmentA = ShiftAssignment::factory()
            ->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($shiftA)->on('2026-09-10')->create();

        $assignmentB = ShiftAssignment::factory()
            ->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($shiftB)->on('2026-09-10')->create();

        // Create two records for the same day so the count badge appears
        AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'shift_assignment_id' => $assignmentA->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-10',
            'shift_name' => $assignmentA->shift_name,
            'checked_in_at' => '2026-09-10 09:00:00',
            'checked_out_at' => '2026-09-10 14:00:00',
            'status' => 'present',
        ]);

        AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'shift_assignment_id' => $assignmentB->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-10',
            'shift_name' => $assignmentB->shift_name,
            'checked_in_at' => '2026-09-10 14:00:00',
            'checked_out_at' => '2026-09-10 20:00:00',
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->employee)
            ->get(route('attendance.board'));

        $response->assertOk();
        $html = $response->getContent();

        // Two records mean the count badge should appear
        $this->assertStringContainsString('neo-cal__badge--count', $html);
    }

    public function test_clock_in_and_clock_out_endpoints_still_work(): void
    {
        Carbon::setTestNow('2026-09-16 08:55:00');
        $shift = WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '19:00:00')->create();

        $assignment = ShiftAssignment::factory()
            ->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($shift)->on('2026-09-16')->create();

        $this->actingAs($this->employee)
            ->postJson(route('attendance.check-in'), [
                'latitude' => 10.7769000,
                'longitude' => 106.7009000,
                'accuracy' => 10,
            ])
            ->assertRedirect(route('attendance.board'));

        Carbon::setTestNow('2026-09-16 19:05:00');

        $this->actingAs($this->employee)
            ->postJson(route('attendance.check-out'), [
                'latitude' => 10.7769000,
                'longitude' => 106.7009000,
                'accuracy' => 10,
            ])
            ->assertRedirect(route('attendance.board'));

        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $this->employee->id,
            'shift_assignment_id' => $assignment->id,
            'work_date' => '2026-09-16 00:00:00',
        ]);
    }

    public function test_month_navigation_links_are_present(): void
    {
        Carbon::setTestNow('2026-09-16');

        $this->actingAs($this->employee)
            ->get(route('attendance.board'))
            ->assertOk()
            ->assertSee(route('attendance.board', ['month' => '2026-08']))
            ->assertSee(route('attendance.board', ['month' => '2026-10']));
    }
}
