<?php

namespace Tests\Feature\Shifts;

use App\Enums\ShiftRequestStatus;
use App\Enums\ShiftRequestType;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftRequestSwapSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_employee_can_submit_a_swap_for_a_counter_shift_on_the_same_branch_and_date(): void
    {
        $branch = Branch::factory()->create();
        $requester = User::factory()->employee()->withoutBranch()->atBranch($branch)->create();
        $recipient = User::factory()->employee()->withoutBranch()->atBranch($branch)->create();
        $shift = WorkShift::factory()->atBranch($branch)->create();

        $ownAssignment = ShiftAssignment::factory()
            ->forEmployee($requester)
            ->atBranch($branch)
            ->usingShift($shift)
            ->on('2026-10-15')
            ->create();
        $counterAssignment = ShiftAssignment::factory()
            ->forEmployee($recipient)
            ->atBranch($branch)
            ->usingShift($shift)
            ->on('2026-10-15')
            ->create();

        $this->actingAs($requester)
            ->get(route('admin.shift-requests.index'))
            ->assertOk()
            ->assertSee('Gửi đề nghị đổi ca')
            ->assertSee('value="'.$counterAssignment->id.'"', false);

        $this->actingAs($requester)
            ->post(route('admin.shift-requests.swap.store'), [
                'shift_assignment_id' => $ownAssignment->id,
                'recipient_id' => $recipient->id,
                'counter_shift_assignment_id' => $counterAssignment->id,
                'reason' => 'Có việc gia đình.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shift_requests', [
            'type' => ShiftRequestType::Swap->value,
            'status' => ShiftRequestStatus::PendingRecipient->value,
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
            'shift_assignment_id' => $ownAssignment->id,
            'counter_shift_assignment_id' => $counterAssignment->id,
        ]);
    }

    public function test_the_swap_form_hides_counter_shifts_that_do_not_match_an_employee_shift_date_and_branch(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $requester = User::factory()->employee()->withoutBranch()->atBranch($branch)->create();
        $recipient = User::factory()->employee()->withoutBranch()->atBranch($branch)->create();
        $otherEmployee = User::factory()->employee()->withoutBranch()->atBranch($otherBranch)->create();
        $shift = WorkShift::factory()->atBranch($branch)->create();
        $otherShift = WorkShift::factory()->atBranch($otherBranch)->create();

        ShiftAssignment::factory()
            ->forEmployee($requester)
            ->atBranch($branch)
            ->usingShift($shift)
            ->on('2026-10-15')
            ->create();
        $eligibleCounter = ShiftAssignment::factory()
            ->forEmployee($recipient)
            ->atBranch($branch)
            ->usingShift($shift)
            ->on('2026-10-15')
            ->create();
        $wrongDate = ShiftAssignment::factory()
            ->forEmployee($recipient)
            ->atBranch($branch)
            ->usingShift($shift)
            ->on('2026-10-16')
            ->create();
        $wrongBranch = ShiftAssignment::factory()
            ->forEmployee($otherEmployee)
            ->atBranch($otherBranch)
            ->usingShift($otherShift)
            ->on('2026-10-15')
            ->create();

        $this->actingAs($requester)
            ->get(route('admin.shift-requests.index'))
            ->assertOk()
            ->assertSee('value="'.$eligibleCounter->id.'"', false)
            ->assertDontSee('value="'.$wrongDate->id.'"', false)
            ->assertDontSee('value="'.$wrongBranch->id.'"', false);
    }
}
