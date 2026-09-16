<?php

namespace Tests\Feature\Admin;

use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-4: Lịch quản trị chấm công.
 *
 * - Overview hiển thị số ca đi làm / vắng trên admin attendance index.
 * - Chọn nhân viên chuyển sang chi tiết cá nhân.
 * - Month navigation hoạt động.
 */
class AdminCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'code' => 'CN-ADM',
            'latitude' => 10.7769000,
            'longitude' => 106.7009000,
            'gps_attendance_enabled' => true,
        ]);

        $this->manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();
        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
    }

    public function test_admin_index_renders_calendar_overview(): void
    {
        Carbon::setTestNow('2026-09-16');

        $this->actingAs($this->manager);

        $shift = WorkShift::factory()->create(['name' => 'Ca Admin']);

        ShiftAssignment::factory()
            ->forEmployee($this->employee)
            ->atBranch($this->branch)
            ->on('2026-09-15')
            ->usingShift($shift)
            ->create();

        AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'work_date' => '2026-09-15',
            'shift_name' => 'Ca Admin',
            'status' => 'present',
        ]);

        $response = $this->get(route('admin.attendance.index', ['month' => '2026-09']));
        $response->assertOk();

        $html = $response->getContent() ?: '';
        $this->assertStringContainsString('Lịch chấm công tháng', $html);
        $this->assertStringContainsString('tháng 9 2026', $html);
    }

    public function test_admin_calendar_shows_record_counts_per_day(): void
    {
        Carbon::setTestNow('2026-09-16');

        $this->actingAs($this->manager);

        $shift = WorkShift::factory()->create(['name' => 'Ca Admin']);

        ShiftAssignment::factory()
            ->forEmployee($this->employee)
            ->atBranch($this->branch)
            ->on('2026-09-15')
            ->usingShift($shift)
            ->create();

        AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'work_date' => '2026-09-15',
            'shift_name' => 'Ca Admin',
            'status' => 'present',
        ]);

        $response = $this->get(route('admin.attendance.index', ['month' => '2026-09']));
        $html = $response->getContent() ?: '';

        $this->assertStringContainsString('1 đi làm', $html);
    }

    public function test_filter_by_employee_switches_to_detail_mode(): void
    {
        Carbon::setTestNow('2026-09-16');

        $this->actingAs($this->manager);

        $shift = WorkShift::factory()->create(['name' => 'Ca Admin']);

        ShiftAssignment::factory()
            ->forEmployee($this->employee)
            ->atBranch($this->branch)
            ->on('2026-09-15')
            ->usingShift($shift)
            ->create();

        AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'work_date' => '2026-09-15',
            'shift_name' => 'Ca Admin',
            'status' => 'present',
        ]);

        $response = $this->get(route('admin.attendance.index', [
            'month' => '2026-09',
            'employee_id' => $this->employee->id,
        ]));
        $response->assertOk();

        $html = $response->getContent() ?: '';
        $this->assertStringContainsString($this->employee->name, $html);
        $this->assertStringContainsString('Ca Admin', $html);
    }

    public function test_month_navigation_links_are_present_on_admin_calendar(): void
    {
        Carbon::setTestNow('2026-09-16');

        $this->actingAs($this->manager);

        $response = $this->get(route('admin.attendance.index', ['month' => '2026-09']));
        $html = $response->getContent() ?: '';

        $this->assertStringContainsString('month=2026-08', $html);
        $this->assertStringContainsString('month=2026-10', $html);
    }

    public function test_admin_calendar_does_not_show_records_from_other_branches(): void
    {
        Carbon::setTestNow('2026-09-16');;

        $otherBranch = Branch::factory()->create(['code' => 'CN-OTH']);
        $otherEmployee = User::factory()->employee()->withoutBranch()->atBranch($otherBranch)->create();

        $this->actingAs($this->manager);

        AttendanceRecord::factory()->create([
            'employee_id' => $otherEmployee->id,
            'branch_id' => $otherBranch->id,
            'work_date' => '2026-09-15',
            'shift_name' => 'Ca Nhánh Khác',
            'status' => 'present',
        ]);

        $response = $this->get(route('admin.attendance.index', ['month' => '2026-09']));
        $html = $response->getContent() ?: '';

        $this->assertStringNotContainsString('Ca Nhánh Khác', $html);
    }
}
