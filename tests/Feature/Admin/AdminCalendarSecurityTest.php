<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 security tests for admin calendar: IDOR, branch scope, self-dealing, invalid month.
 */
class AdminCalendarSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'code' => 'CN-SEC',
            'latitude' => 10.7769000,
            'longitude' => 106.7009000,
            'gps_attendance_enabled' => true,
        ]);

        $this->manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();
        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
    }

    public function test_manager_cannot_view_employee_outside_branch_via_query_string(): void
    {
        $otherBranch = Branch::factory()->create(['code' => 'CN-OTH']);
        $otherEmployee = User::factory()->employee()->withoutBranch()->atBranch($otherBranch)->create();

        // Create attendance for the other employee so data exists
        AttendanceRecord::factory()->create([
            'employee_id' => $otherEmployee->id,
            'branch_id' => $otherBranch->id,
            'work_date' => now()->toDateString(),
            'shift_name' => 'Ca Ngoài',
            'status' => 'present',
        ]);

        $this->actingAs($this->manager);

        $response = $this->get(route('admin.attendance.index', [
            'month' => now()->format('Y-m'),
            'employee_id' => $otherEmployee->id,
        ]));

        // Should not show the other employee's data
        $html = $response->getContent() ?: '';
        $this->assertStringNotContainsString('Ca Ngoài', $html);
    }

    public function test_manager_can_view_employee_inside_branch(): void
    {
        AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(),
            'shift_name' => 'Ca Trong',
            'status' => 'present',
        ]);

        $this->actingAs($this->manager);

        $response = $this->get(route('admin.attendance.index', [
            'month' => now()->format('Y-m'),
            'employee_id' => $this->employee->id,
        ]));

        $html = $response->getContent() ?: '';
        $this->assertStringContainsString('Ca Trong', $html);
    }

    public function test_manager_cannot_see_self_dealing_actions_in_calendar(): void
    {
        // Manager creates their own attendance record
        AttendanceRecord::factory()->create([
            'employee_id' => $this->manager->id,
            'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(),
            'shift_name' => 'Ca Manager',
            'status' => 'present',
        ]);

        $this->actingAs($this->manager);

        $response = $this->get(route('admin.attendance.index', [
            'month' => now()->format('Y-m'),
            'employee_id' => $this->manager->id,
        ]));

        $html = $response->getContent() ?: '';
        // Should not contain edit link for manager's own record
        $this->assertStringNotContainsString(route('admin.attendance.edit', ['attendance' => AttendanceRecord::first()]), $html);
    }

    public function test_invalid_month_does_not_cause_500(): void
    {
        $this->actingAs($this->manager);

        $invalidMonths = [
            'not-a-month',
            '2025-00',
            '2025-13',
            '2025/03',
            'abc',
            '',
        ];

        foreach ($invalidMonths as $month) {
            $response = $this->get(route('admin.attendance.index', ['month' => $month]));
            $response->assertOk();
        }
    }
}
