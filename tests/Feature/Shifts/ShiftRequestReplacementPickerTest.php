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

class ShiftRequestReplacementPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_sees_only_eligible_replacement_candidates_for_an_approved_leave(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->manager()->withoutBranch()->atBranch($branch)->create();
        $requester = User::factory()->employee()->withoutBranch()->atBranch($branch)->create();
        $eligible = User::factory()->employee()->withoutBranch()->atBranch($branch)->create(['name' => 'Người phù hợp']);
        $busy = User::factory()->employee()->withoutBranch()->atBranch($branch)->create(['name' => 'Người bận']);
        $shift = WorkShift::factory()->atBranch($branch)->spanning('09:00', '17:00')->create();
        $assignment = ShiftAssignment::factory()
            ->forEmployee($requester)
            ->atBranch($branch)
            ->usingShift($shift)
            ->on('2026-10-15')
            ->create();
        ShiftAssignment::factory()
            ->forEmployee($busy)
            ->atBranch($branch)
            ->usingShift($shift)
            ->on('2026-10-15')
            ->create();
        $request = ShiftRequest::factory()->forAssignment($assignment)->leave()->create([
            'status' => ShiftRequestStatus::Approved,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.shift-requests.index'))
            ->assertOk()
            ->assertSee('Chọn người thay')
            ->assertSee($eligible->name)
            ->assertDontSee($busy->name);

        $this->assertSame(ShiftRequestStatus::Approved, $request->status);
    }
}
