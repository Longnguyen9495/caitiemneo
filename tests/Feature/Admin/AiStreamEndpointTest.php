<?php

namespace Tests\Feature\Admin;

use App\Enums\AiActionStatus;
use App\Models\AiActionProposal;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Branch;
use App\Models\User;
use App\Services\Ai\AiPromptBuilder;
use App\Services\Ai\AiProviderResult;
use App\Services\Ai\Concerns\DelegatesConverseToChat;
use App\Services\Ai\Contracts\AiProvider;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class AiStreamEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.enabled', true);
        RateLimiter::clear('ai.messages.stream');
        Session::forget('admin.current_branch_id');
        app()->forgetInstance(BranchContext::class);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('ai.messages.stream');
        parent::tearDown();
    }

    private function fakeProvider(string $content = 'Doanh thu hôm nay là 5 triệu đồng.'): void
    {
        $this->app->instance(AiProvider::class, new class($content) implements AiProvider
        {
            use DelegatesConverseToChat;

            public function __construct(private string $content) {}

            public function chat(array $messages): AiProviderResult
            {
                return new AiProviderResult(
                    content: $this->content,
                    blocks: [['type' => 'text', 'content' => $this->content]],
                    actions: [],
                    provider: 'fake',
                    model: 'fake-model',
                    promptTokens: 10,
                    completionTokens: 5,
                    totalTokens: 15,
                    latencyMs: 3,
                    providerReference: 'resp-1',
                );
            }
        });
    }

    /**
     * Gom toàn bộ thân phản hồi phát dần thành một chuỗi để kiểm tra.
     */
    private function streamBody(TestResponse $response): string
    {
        return (string) $response->streamedContent();
    }

    public function test_the_stream_endpoint_emits_the_rendered_message(): void
    {
        $this->fakeProvider();
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)
            ->post(route('admin.ai.messages.stream'), ['message' => 'Doanh thu hôm nay'])
            ->assertOk();

        $body = $this->streamBody($response);

        $this->assertStringContainsString('event: conversation', $body);
        $this->assertStringContainsString('event: message', $body);
        $this->assertStringContainsString('Doanh thu hôm nay là 5 triệu đồng.', $body);

        $this->assertDatabaseCount('ai_messages', 2);
        $this->assertDatabaseHas('ai_messages', ['role' => 'user', 'content' => 'Doanh thu hôm nay']);
        $this->assertDatabaseHas('ai_messages', ['role' => 'assistant', 'model' => 'fake-model']);
    }

    public function test_a_provider_failure_is_reported_but_keeps_the_question(): void
    {
        $this->app->instance(AiProvider::class, new class implements AiProvider
        {
            use DelegatesConverseToChat;

            public function chat(array $messages): AiProviderResult
            {
                throw new RuntimeException('Provider unavailable');
            }
        });

        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)
            ->post(route('admin.ai.messages.stream'), ['message' => 'Tóm tắt hôm nay'])
            ->assertOk();

        $body = $this->streamBody($response);

        $this->assertStringContainsString('event: failed', $body);

        // Câu hỏi phải còn lại để người dùng bấm gửi lại mà không phải gõ lại.
        $this->assertDatabaseCount('ai_messages', 1);
        $this->assertDatabaseHas('ai_messages', ['role' => 'user', 'content' => 'Tóm tắt hôm nay']);
    }

    public function test_an_employee_cannot_use_the_stream_endpoint(): void
    {
        $this->fakeProvider();

        $this->actingAs(User::factory()->employee()->create())
            ->post(route('admin.ai.messages.stream'), ['message' => 'Doanh thu'])
            ->assertForbidden();
    }

    public function test_a_user_cannot_stream_into_another_users_conversation(): void
    {
        $this->fakeProvider();

        $owner = User::factory()->owner()->create();
        $other = User::factory()->manager()->create();
        $conversation = AiConversation::query()->create([
            'user_id' => $other->id,
            'scope_branch_ids' => [],
            'last_message_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('admin.ai.messages.stream'), [
                'conversation_id' => $conversation->id,
                'message' => 'Xin chào',
            ])
            ->assertNotFound();
    }

    public function test_an_empty_message_is_rejected_before_the_stream_opens(): void
    {
        $this->fakeProvider();
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post(route('admin.ai.messages.stream'), ['message' => ''])
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('ai_messages', 0);
    }

    public function test_the_message_partial_endpoint_refuses_another_users_message(): void
    {
        $owner = User::factory()->owner()->create();
        $other = User::factory()->manager()->create();

        $conversation = AiConversation::query()->create([
            'user_id' => $other->id,
            'scope_branch_ids' => [],
            'last_message_at' => now(),
        ]);

        $message = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => 'Nội dung riêng tư',
            'blocks' => [['type' => 'text', 'content' => 'Nội dung riêng tư']],
        ]);

        $this->actingAs($owner)
            ->getJson(route('admin.ai.messages.show', $message))
            ->assertNotFound();
    }

    public function test_rejecting_a_proposal_over_ajax_returns_the_updated_message(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $owner = User::factory()->owner()->atBranch($branch)->create();

        $conversation = AiConversation::query()->create([
            'user_id' => $owner->id,
            'branch_id' => $branch->id,
            'scope_branch_ids' => [$branch->id],
            'last_message_at' => now(),
        ]);

        $message = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => 'Đề xuất ghi khoản chi.',
            'blocks' => [['type' => 'text', 'content' => 'Đề xuất ghi khoản chi.']],
        ]);

        $proposal = AiActionProposal::query()->create([
            'message_id' => $message->id,
            'proposed_by' => $owner->id,
            'branch_id' => $branch->id,
            'type' => 'create_cash_entry',
            'summary' => 'Ghi khoản chi marketing',
            'payload' => ['branch_id' => $branch->id, 'type' => 'expense', 'amount' => 500000],
            'status' => AiActionStatus::Pending,
            'idempotency_key' => (string) Str::uuid(),
            'request_fingerprint' => hash('sha256', 'test'),
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $branch->id])
            ->withConfirmedPassword()
            ->postJson(route('admin.ai.actions.reject', $proposal))
            ->assertOk()
            ->assertJsonStructure(['status', 'message', 'html']);

        // Trả về HTML đã cập nhật để trang vẽ lại đúng khối đó, thay vì nạp lại
        // cả trang chỉ để đổi một cái nhãn trạng thái.
        $this->assertStringContainsString('Đề xuất ghi khoản chi.', $response->json('html'));
        $this->assertSame(AiActionStatus::Rejected, $proposal->fresh()->status);
    }

    public function test_the_conversation_history_carries_the_data_digest_forward(): void
    {
        $owner = User::factory()->owner()->create();

        $conversation = AiConversation::query()->create([
            'user_id' => $owner->id,
            'scope_branch_ids' => [],
            'last_message_at' => now(),
        ]);

        // Lượt trước đã tra doanh thu; lượt sau hỏi nối tiếp phải còn thấy dấu
        // vết đó, nếu không model trả lời như chưa từng xem gì.
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => 'Doanh thu tháng này là 12 triệu.',
            'blocks' => [],
            'metadata' => ['data_digest' => 'get_invoices (2026-09-01 → 2026-09-30)'],
        ]);

        $messages = app(AiPromptBuilder::class)
            ->build($owner, $conversation, 'còn chi nhánh kia thì sao');

        $assistantTurn = collect($messages)->firstWhere('role', 'assistant');

        $this->assertStringContainsString('get_invoices', $assistantTurn['content']);
    }

    public function test_a_message_without_a_digest_is_sent_unchanged(): void
    {
        $owner = User::factory()->owner()->create();

        $conversation = AiConversation::query()->create([
            'user_id' => $owner->id,
            'scope_branch_ids' => [],
            'last_message_at' => now(),
        ]);

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => 'Chào bạn.',
            'blocks' => [],
        ]);

        $messages = app(AiPromptBuilder::class)
            ->build($owner, $conversation, 'tiếp tục');

        $assistantTurn = collect($messages)->firstWhere('role', 'assistant');

        $this->assertSame('Chào bạn.', $assistantTurn['content']);
    }

    public function test_assistant_messages_record_which_tools_were_used(): void
    {
        $owner = User::factory()->owner()->create();

        $this->app->instance(AiProvider::class, new class implements AiProvider
        {
            use DelegatesConverseToChat;

            public function chat(array $messages): AiProviderResult
            {
                return new AiProviderResult(
                    content: 'Đã xem doanh thu.',
                    blocks: [['type' => 'text', 'content' => 'Đã xem doanh thu.']],
                    actions: [],
                    provider: 'fake',
                    model: 'fake-model',
                    toolsUsed: [['tool' => 'get_invoices', 'arguments' => ['from' => '2026-09-01', 'to' => '2026-09-30']]],
                );
            }
        });

        $this->actingAs($owner)
            ->post(route('admin.ai.messages.store'), ['message' => 'doanh thu tháng này'])
            ->assertRedirect();

        $assistant = AiMessage::query()->where('role', 'assistant')->sole();

        $this->assertSame('get_invoices', $assistant->metadata['tools_used'][0]['tool']);
        $this->assertStringContainsString('get_invoices', $assistant->metadata['data_digest']);
    }
}
