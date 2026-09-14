<?php

namespace Tests\Feature\Shifts;

use App\Enums\ShiftRequestStatus;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequest;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftRequestNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_sees_the_branch_scoped_shift_request_badge(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $manager = User::factory()->manager()->withoutBranch()->atBranch($branch)->create();
        $employee = User::factory()->employee()->withoutBranch()->atBranch($branch)->create();
        $otherEmployee = User::factory()->employee()->withoutBranch()->atBranch($otherBranch)->create();
        $shift = WorkShift::factory()->atBranch($branch)->create();
        $otherShift = WorkShift::factory()->atBranch($otherBranch)->create();

        $pendingAssignment = ShiftAssignment::factory()
            ->forEmployee($employee)
            ->atBranch($branch)
            ->usingShift($shift)
            ->on('2026-10-10')
            ->create();
        ShiftRequest::factory()->forAssignment($pendingAssignment)->leave()->create([
            'status' => ShiftRequestStatus::PendingApproval,
        ]);

        $approvedAssignment = ShiftAssignment::factory()
            ->forEmployee($employee)
            ->atBranch($branch)
            ->usingShift($shift)
            ->on('2026-10-11')
            ->create();
        ShiftRequest::factory()->forAssignment($approvedAssignment)->leave()->create([
            'status' => ShiftRequestStatus::Approved,
        ]);

        $otherAssignment = ShiftAssignment::factory()
            ->forEmployee($otherEmployee)
            ->atBranch($otherBranch)
            ->usingShift($otherShift)
            ->on('2026-10-12')
            ->create();
        ShiftRequest::factory()->forAssignment($otherAssignment)->leave()->create([
            'status' => ShiftRequestStatus::PendingApproval,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Đơn ca & nghỉ')
            ->assertSee('aria-label="2 đơn cần xử lý"', false);
    }

    public function test_an_employee_does_not_see_a_management_shift_request_badge(): void
    {
        $branch = Branch::factory()->create();
        $employee = User::factory()->employee()->withoutBranch()->atBranch($branch)->create();

        $this->actingAs($employee)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Đơn ca & nghỉ')
            ->assertDontSee('đơn cần xử lý');
    }
}
