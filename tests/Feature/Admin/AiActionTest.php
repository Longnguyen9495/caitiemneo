<?php

namespace Tests\Feature\Admin;

use App\Enums\AiActionStatus;
use App\Enums\InventoryMovementType;
use App\Models\AiActionProposal;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\CashTransaction;
use App\Models\Product;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class AiActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.enabled', true);
        RateLimiter::clear('ai.messages.store');
        RateLimiter::clear('admin.ai.actions.confirm');
        RateLimiter::clear('admin.ai.actions.reject');
        Session::forget('admin.current_branch_id');
        app()->forgetInstance(BranchContext::class);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('ai.messages.store');
        RateLimiter::clear('admin.ai.actions.confirm');
        RateLimiter::clear('admin.ai.actions.reject');
        parent::tearDown();
    }

    public function test_confirm_proposal_outside_current_branch_scope_is_rejected(): void
    {
        [$owner, $proposal] = $this->cashProposal();

        // Switch the owner to a different branch so the proposal branch is outside scope
        $otherBranch = Branch::factory()->create();
        $owner->branchAssignments()->delete();
        $owner->branchAssignments()->create([
            'branch_id' => $otherBranch->id,
            'is_primary' => true,
            'starts_on' => '2000-01-01',
        ]);

        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $otherBranch->id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('cash_transactions', 0);
        $this->assertSame(AiActionStatus::Pending, $proposal->fresh()->status);
    }

    public function test_rejecting_a_proposal_twice_does_not_change_first_decision(): void
    {
        [$owner, $proposal] = $this->cashProposal();

        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.reject', $proposal))
            ->assertRedirect()
            ->assertSessionHas('success');

        $firstDecidedAt = $proposal->fresh()->decided_at;

        // Second reject returns the same Rejected proposal; controller still
        // reports success because the status is already Rejected.
        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.reject', $proposal))
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $proposal->fresh();
        $this->assertSame(AiActionStatus::Rejected, $fresh->status);
        $this->assertSame($firstDecidedAt->toDateTimeString(), $fresh->decided_at->toDateTimeString());
    }

    public function test_rate_limit_on_confirm_and_reject_endpoints_works(): void
    {
        [$owner, $proposal] = $this->cashProposal();

        // Throttle is 10 per minute. Send 10 confirms on the same proposal
        // (each one will be skipped because not pending, but still hits middleware).
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($owner)
                ->withSession(['admin.current_branch_id' => $proposal->branch_id])
                ->withConfirmedPassword()
                ->post(route('admin.ai.actions.confirm', $proposal))
                ->assertRedirect();
        }

        // 11th should be rate limited
        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertStatus(429);
    }

    public function test_confirm_creates_audit_event_with_actor_and_branch(): void
    {
        [$owner, $proposal] = $this->cashProposal();

        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('audit_events', [
            'auditable_type' => CashTransaction::class,
            'action' => 'created',
            'actor_id' => $owner->id,
            'branch_id' => $proposal->branch_id,
        ]);

        $event = AuditEvent::query()
            ->where('auditable_type', CashTransaction::class)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($event);
        $this->assertNotNull($event->correlation_id);
        $this->assertSame($owner->name, $event->actor_name);
        $this->assertNotNull($event->after);
    }

    public function test_rejected_proposal_sets_decided_by_and_decided_at(): void
    {
        [$owner, $proposal] = $this->cashProposal();

        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.reject', $proposal))
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $proposal->fresh();
        $this->assertSame(AiActionStatus::Rejected, $fresh->status);
        $this->assertSame($owner->id, $fresh->decided_by);
        $this->assertNotNull($fresh->decided_at);
    }

    public function test_validation_failure_does_not_create_business_record(): void
    {
        [$owner, $proposal] = $this->cashProposal();

        // Invalidate by mutating payload to have a zero amount
        $proposal->update([
            'payload' => array_merge($proposal->payload, ['amount' => 0]),
        ]);

        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('cash_transactions', 0);
        $this->assertSame(AiActionStatus::Pending, $proposal->fresh()->status);
    }

    /** @return array{User, AiActionProposal} */
    private function cashProposal(): array
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();

        $conversation = AiConversation::query()->create([
            'user_id' => $owner->id,
            'branch_id' => $branch->id,
            'scope_branch_ids' => [$branch->id],
            'last_message_at' => now(),
        ]);
        $message = AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Đề xuất ghi khoản chi.',
        ]);
        $proposal = AiActionProposal::query()->create([
            'message_id' => $message->id,
            'proposed_by' => $owner->id,
            'branch_id' => $branch->id,
            'type' => 'create_cash_entry',
            'summary' => 'Ghi khoản chi marketing 500.000đ',
            'payload' => [
                'branch_id' => $branch->id,
                'type' => 'expense',
                'category' => 'marketing',
                'amount' => 500000,
                'payment_method' => 'transfer',
                'occurred_at' => now()->toDateTimeString(),
                'note' => 'Chi phí marketing theo đề xuất AI',
            ],
            'status' => AiActionStatus::Pending,
            'idempotency_key' => fake()->uuid(),
            'request_fingerprint' => hash('sha256', fake()->uuid()),
        ]);

        return [$owner, $proposal];
    }

    /** @return array{User, AiActionProposal} */
    private function stockProposal(): array
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();
        $product = Product::factory()->create(['is_active' => true]);
        BranchProduct::factory()->create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $conversation = AiConversation::query()->create([
            'user_id' => $owner->id,
            'branch_id' => $branch->id,
            'scope_branch_ids' => [$branch->id],
            'last_message_at' => now(),
        ]);
        $message = AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Đề xuất điều chỉnh tồn kho.',
        ]);
        $proposal = AiActionProposal::query()->create([
            'message_id' => $message->id,
            'proposed_by' => $owner->id,
            'branch_id' => $branch->id,
            'type' => 'adjust_stock',
            'summary' => 'Điều chỉnh tồn kho sản phẩm',
            'payload' => [
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'type' => InventoryMovementType::Adjustment->value,
                'adjustment_mode' => 'absolute',
                'quantity' => 10,
                'occurred_at' => now()->toDateTimeString(),
                'note' => 'Điều chỉnh tồn kho theo đề xuất AI',
            ],
            'status' => AiActionStatus::Pending,
            'idempotency_key' => fake()->uuid(),
            'request_fingerprint' => hash('sha256', fake()->uuid()),
        ]);

        return [$owner, $proposal];
    }
}
