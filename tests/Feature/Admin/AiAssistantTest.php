<?php

namespace Tests\Feature\Admin;

use App\Actions\Cash\RecordCashTransactionAction;
use App\Enums\AiActionStatus;
use App\Enums\InventoryMovementType;
use App\Models\AiActionProposal;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\CashTransaction;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\AiProviderResult;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Audit\AuditRecorder;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use Tests\TestCase;

class AiAssistantTest extends TestCase
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

    public function test_only_leadership_can_open_the_ai_assistant(): void
    {
        $this->actingAs(User::factory()->employee()->create())
            ->get(route('admin.ai.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('admin.ai.index'))
            ->assertOk()
            ->assertSee('Trợ lý quản lý Cái Tiệm Neo');
    }

    public function test_a_user_cannot_open_another_users_conversation(): void
    {
        $owner = User::factory()->owner()->create();
        $other = User::factory()->manager()->create();
        $conversation = AiConversation::query()->create([
            'user_id' => $other->id,
            'scope_branch_ids' => $other->accessibleBranchIds(),
            'last_message_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('admin.ai.index', ['conversation' => $conversation->id]))
            ->assertNotFound();
    }

    public function test_provider_response_blocks_telemetry_and_action_are_persisted(): void
    {
        $this->app->instance(AiProvider::class, new class implements AiProvider
        {
            public function chat(array $messages): AiProviderResult
            {
                return new AiProviderResult(
                    content: 'Doanh thu tháng này là 12 triệu đồng.',
                    blocks: [
                        ['type' => 'text', 'content' => 'Tóm tắt doanh thu'],
                        [
                            'type' => 'chart',
                            'chart_type' => 'bar',
                            'title' => 'Doanh thu',
                            'labels' => ['Tháng này'],
                            'datasets' => [['label' => 'Doanh thu', 'data' => [12000000]]],
                        ],
                    ],
                    actions: [[
                        'type' => 'create_cash_entry',
                        'summary' => 'Ghi khoản chi marketing',
                        'payload' => [
                            'branch_id' => 1,
                            'type' => 'expense',
                            'category' => 'marketing',
                            'amount' => 500000,
                            'occurred_at' => now()->toDateTimeString(),
                            'note' => 'Chi phí marketing theo đề xuất AI',
                        ],
                    ]],
                    provider: 'fake',
                    model: 'fake-model',
                    promptTokens: 100,
                    completionTokens: 50,
                    totalTokens: 150,
                    latencyMs: 25,
                    providerReference: 'response-1',
                );
            }
        });

        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post(route('admin.ai.messages.store'), ['message' => 'Tóm tắt tháng này'])
            ->assertRedirect();

        $conversation = AiConversation::query()->sole();
        $this->assertSame($owner->id, $conversation->user_id);
        $this->assertSame('Tóm tắt tháng này', $conversation->title);
        $this->assertDatabaseCount('ai_messages', 2);

        $assistant = AiMessage::query()->where('role', 'assistant')->sole();
        $this->assertSame('fake-model', $assistant->model);
        $this->assertSame(150, $assistant->total_tokens);
        $this->assertSame('chart', $assistant->blocks[1]['type']);
        $this->assertDatabaseHas('ai_action_proposals', [
            'message_id' => $assistant->id,
            'type' => 'create_cash_entry',
            'status' => 'pending',
        ]);
    }

    public function test_provider_failure_keeps_the_user_message(): void
    {
        $this->app->instance(AiProvider::class, new class implements AiProvider
        {
            public function chat(array $messages): AiProviderResult
            {
                throw new RuntimeException('Provider unavailable');
            }
        });

        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post(route('admin.ai.messages.store'), ['message' => 'Tóm tắt hôm nay'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('ai_conversations', 1);
        $this->assertDatabaseCount('ai_messages', 1);
        $this->assertDatabaseHas('ai_messages', [
            'role' => 'user',
            'content' => 'Tóm tắt hôm nay',
        ]);
    }

    public function test_action_confirmation_requires_a_recent_password_confirmation(): void
    {
        [$owner, $proposal] = $this->cashProposal();

        $this->actingAs($owner)
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertRedirect(route('password.confirm'));

        $this->assertDatabaseCount('cash_transactions', 0);
        $this->assertSame(AiActionStatus::Pending, $proposal->fresh()->status);
    }

    public function test_a_confirmed_cash_action_executes_exactly_once(): void
    {
        [$owner, $proposal] = $this->cashProposal();

        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('cash_transactions', 1);

        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertRedirect();

        $this->assertDatabaseCount('cash_transactions', 1);
        $this->assertSame(AiActionStatus::Executed, $proposal->fresh()->status);
        $this->assertSame($owner->id, $proposal->fresh()->decided_by);
        $this->assertInstanceOf(CashTransaction::class, $proposal->fresh()->result);
    }

    public function test_a_rejected_action_cannot_be_executed_later(): void
    {
        [$owner, $proposal] = $this->cashProposal();

        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.reject', $proposal))
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertRedirect();

        $this->assertDatabaseCount('cash_transactions', 0);
        $this->assertSame(AiActionStatus::Rejected, $proposal->fresh()->status);
    }

    public function test_another_leader_cannot_confirm_the_proposal(): void
    {
        [, $proposal] = $this->cashProposal();
        $other = User::factory()->owner()->create();

        $this->actingAs($other)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertNotFound();

        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_a_failed_action_cannot_be_retried(): void
    {
        [$owner, $proposal] = $this->cashProposal();

        $this->app->bind(RecordCashTransactionAction::class, function () {
            return new class(resolve(AuditRecorder::class)) extends RecordCashTransactionAction
            {
                public function handle(array $data, User $actor, ?CashTransaction $transaction = null): CashTransaction
                {
                    throw new RuntimeException('Domain action failed.');
                }
            };
        });

        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('cash_transactions', 0);
        $this->assertSame(AiActionStatus::Failed, $proposal->fresh()->status);

        // Re-allow the gate and try again – must stay failed.
        $this->app->bind(RecordCashTransactionAction::class, function () {
            return new class(resolve(AuditRecorder::class)) extends RecordCashTransactionAction
            {
                public function handle(array $data, User $actor, ?CashTransaction $transaction = null): CashTransaction
                {
                    throw new \LogicException('Should not reach here because proposal is already Failed.');
                }
            };
        });

        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseCount('cash_transactions', 0);
        $this->assertSame(AiActionStatus::Failed, $proposal->fresh()->status);
    }

    public function test_a_failed_action_leaves_no_business_record(): void
    {
        [$owner, $proposal] = $this->cashProposal();

        $this->app->bind(RecordCashTransactionAction::class, function () {
            return new class(resolve(AuditRecorder::class)) extends RecordCashTransactionAction
            {
                public function handle(array $data, User $actor, ?CashTransaction $transaction = null): CashTransaction
                {
                    throw new RuntimeException('Domain action failed.');
                }
            };
        });

        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('cash_transactions', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(AiActionStatus::Failed, $proposal->fresh()->status);
        $this->assertNotNull($proposal->fresh()->failure_message);
    }

    public function test_payload_branch_must_match_proposal_branch(): void
    {
        [$owner, $proposal] = $this->cashProposal();

        // Mutate payload branch to a different value
        $proposal->update([
            'payload' => array_merge($proposal->payload, ['branch_id' => 99999]),
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

    public function test_stock_adjustment_requires_product_in_branch_catalog(): void
    {
        [$owner, $proposal] = $this->stockProposal();

        // Remove the branch-product link so the product is no longer in this branch catalog
        BranchProduct::query()
            ->where('branch_id', $proposal->branch_id)
            ->where('product_id', $proposal->payload['product_id'])
            ->delete();

        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(AiActionStatus::Pending, $proposal->fresh()->status);
    }

    public function test_stock_adjustment_requires_active_product_in_branch(): void
    {
        [$owner, $proposal] = $this->stockProposal();

        // Deactivate the product at this branch
        BranchProduct::query()
            ->where('branch_id', $proposal->branch_id)
            ->where('product_id', $proposal->payload['product_id'])
            ->update(['is_active' => false]);

        $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(AiActionStatus::Pending, $proposal->fresh()->status);
    }

    public function test_an_employee_cannot_confirm_via_ai(): void
    {
        [$owner, $proposal] = $this->cashProposal();
        $employee = User::factory()->employee()->atBranch($proposal->branch)->create();

        $this->actingAs($employee)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertForbidden();

        $this->assertDatabaseCount('cash_transactions', 0);
        $this->assertSame(AiActionStatus::Pending, $proposal->fresh()->status);
    }

    public function test_an_outsider_gets_404_for_proposal(): void
    {
        [$owner, $proposal] = $this->cashProposal();
        $outsider = User::factory()->owner()->create();

        $this->actingAs($outsider)
            ->withSession(['admin.current_branch_id' => $proposal->branch_id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal))
            ->assertNotFound();

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
