<?php

namespace Tests\Feature\Shifts;

use App\Enums\ShiftRequestStatus;
use App\Enums\ShiftRequestType;
use App\Models\Branch;
use App\Models\EmployeeBranchAssignment;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequest;
use App\Models\User;
use App\Services\Shifts\ShiftRequestNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ShiftRequestNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_only_managers_in_the_request_branch(): void
    {
        Notification::fake();

        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $requester = User::factory()->employee()->withoutBranch()->atBranch($branch)->create();
        $manager = User::factory()->manager()->withoutBranch()->create();
        $otherManager = User::factory()->manager()->withoutBranch()->create();
        EmployeeBranchAssignment::factory()->create([
            'user_id' => $manager->id,
            'branch_id' => $branch->id,
            'starts_on' => '2026-01-01',
        ]);
        EmployeeBranchAssignment::factory()->create([
            'user_id' => $otherManager->id,
            'branch_id' => $otherBranch->id,
            'starts_on' => '2026-01-01',
        ]);
        $assignment = ShiftAssignment::factory()
            ->forEmployee($requester)
            ->atBranch($branch)
            ->on('2026-10-15')
            ->create();
        $request = ShiftRequest::factory()
            ->forAssignment($assignment)
            ->create([
                'type' => ShiftRequestType::Leave,
                'status' => ShiftRequestStatus::PendingApproval,
            ]);

        $recipients = app(ShiftRequestNotifier::class)->managersFor($request->fresh());

        $this->assertTrue($recipients->contains('id', $manager->id));
        $this->assertFalse($recipients->contains('id', $otherManager->id));
        $this->assertFalse($recipients->contains('id', $requester->id));
    }

    public function test_it_defers_notification_while_a_transaction_is_open(): void
    {
        Notification::fake();

        $branch = Branch::factory()->create();
        $manager = User::factory()->manager()->withoutBranch()->atBranch($branch)->create();
        $request = ShiftRequest::factory()->create([
            'branch_id' => $branch->id,
            'work_date' => '2026-10-15',
        ]);

        DB::transaction(function () use ($request, $manager): void {
            app(ShiftRequestNotifier::class)->afterCommit($request, [$manager], 'Chờ commit.');

            Notification::assertNothingSent();
        });
    }
}
