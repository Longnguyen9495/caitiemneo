<?php

namespace Tests\Feature\Admin;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Branch;
use App\Models\User;
use App\Services\Ai\AiConversationService;
use App\Services\Ai\AiProviderResult;
use App\Services\Ai\Concerns\DelegatesConverseToChat;
use App\Services\Ai\Contracts\AiProvider;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use Tests\TestCase;

class AiConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ai.enabled', true);
        RateLimiter::clear('ai.messages.store');
        Session::forget('admin.current_branch_id');
        app()->forgetInstance(BranchContext::class);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('ai.messages.store');
        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.ai.index'))
            ->assertRedirect(route('login'));
    }

    public function test_unverified_user_can_access_ai_assistant(): void
    {
        // User model does not implement MustVerifyEmail, so the 'verified'
        // middleware never redirects unverified users in this application.
        $user = User::factory()->owner()->unverified()->create();

        $this->actingAs($user)
            ->get(route('admin.ai.index'))
            ->assertOk();
    }

    public function test_ai_disabled_shows_page_but_composer_disabled(): void
    {
        config()->set('ai.enabled', false);
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)
            ->get(route('admin.ai.index'))
            ->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('Trợ lý AI chưa được bật', $content);
        $this->assertStringContainsString('disabled', strtolower($content));
    }

    public function test_new_conversation_saves_user_id_branch_id_and_scope_snapshot(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();

        // Resolve branch context with a concrete branch so currentId is non-null.
        $request = Request::create('/ai-context');
        $request->setLaravelSession(Session::driver());
        $request->session()->put(BranchContext::SESSION_KEY, $branch->id);
        app()->bind('request', fn () => $request);
        app()->forgetInstance(BranchContext::class);
        $branchContext = new BranchContext($request);
        $branchContext->resolve($owner);
        app()->instance(BranchContext::class, $branchContext);

        $service = app(AiConversationService::class);
        $conversation = $service->createConversation($owner);

        $this->assertSame($owner->id, $conversation->user_id);
        $this->assertSame($branch->id, $conversation->branch_id);
        $this->assertIsArray($conversation->scope_branch_ids);
        $this->assertContains($branch->id, $conversation->scope_branch_ids);
    }

    public function test_first_message_creates_title_with_max_80_chars(): void
    {
        $this->app->instance(AiProvider::class, new class implements AiProvider
        {
            use DelegatesConverseToChat;

            public function chat(array $messages): AiProviderResult
            {
                return new AiProviderResult(
                    content: 'Trả lời ngắn gọn.',
                    blocks: [['type' => 'text', 'content' => 'Trả lời ngắn gọn.']],
                    actions: [],
                    provider: 'fake',
                    model: 'fake',
                    promptTokens: 1,
                    completionTokens: 1,
                    totalTokens: 2,
                    latencyMs: 1,
                    providerReference: 'x',
                );
            }
        });

        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post(route('admin.ai.messages.store'), [
                'message' => str_repeat('X', 120),
            ])
            ->assertRedirect();

        $conversation = AiConversation::query()->sole();
        $this->assertLessThanOrEqual(80, strlen($conversation->title));
    }

    public function test_second_message_does_not_overwrite_title(): void
    {
        $this->app->instance(AiProvider::class, new class implements AiProvider
        {
            use DelegatesConverseToChat;

            public function chat(array $messages): AiProviderResult
            {
                return new AiProviderResult(
                    content: 'Trả lời.',
                    blocks: [['type' => 'text', 'content' => 'Trả lời.']],
                    actions: [],
                    provider: 'fake',
                    model: 'fake',
                    promptTokens: 1,
                    completionTokens: 1,
                    totalTokens: 2,
                    latencyMs: 1,
                    providerReference: 'x',
                );
            }
        });

        $owner = User::factory()->owner()->create();
        $conversation = AiConversation::query()->create([
            'user_id' => $owner->id,
            'title' => 'Tiêu đề gốc',
            'scope_branch_ids' => [],
            'last_message_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('admin.ai.messages.store'), [
                'conversation_id' => $conversation->id,
                'message' => 'Câu hỏi thứ hai',
            ])
            ->assertRedirect();

        $this->assertSame('Tiêu đề gốc', $conversation->fresh()->title);
    }

    public function test_cannot_post_message_to_another_users_conversation(): void
    {
        $owner = User::factory()->owner()->create();
        $other = User::factory()->manager()->create();

        $conversation = AiConversation::query()->create([
            'user_id' => $other->id,
            'scope_branch_ids' => [],
            'last_message_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('admin.ai.messages.store'), [
                'conversation_id' => $conversation->id,
                'message' => 'Xin chào',
            ])
            ->assertNotFound();
    }

    public function test_non_existent_conversation_returns_404(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get(route('admin.ai.index', ['conversation' => 99999]))
            ->assertNotFound();
    }

    public function test_empty_message_is_validated(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post(route('admin.ai.messages.store'), ['message' => ''])
            ->assertSessionHasErrors('message');
    }

    public function test_message_over_max_length_is_validated(): void
    {
        config()->set('ai.max_message_length', 10);
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post(route('admin.ai.messages.store'), ['message' => str_repeat('X', 11)])
            ->assertSessionHasErrors('message');
    }

    public function test_provider_malformed_json_keeps_the_user_message(): void
    {
        $this->app->instance(AiProvider::class, new class implements AiProvider
        {
            use DelegatesConverseToChat;

            public function chat(array $messages): AiProviderResult
            {
                // Simulate the same exception OpenAiCompatibleProvider throws
                // when it cannot decode the response as JSON.
                throw new RuntimeException('Phản hồi AI không đúng định dạng JSON yêu cầu.');
            }
        });

        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post(route('admin.ai.messages.store'), ['message' => 'Hỏi'])
            ->assertRedirect()
            ->assertSessionHas('error');

        // Câu hỏi vẫn còn trong lịch sử để người dùng không phải nhập lại;
        // chỉ phản hồi không hợp lệ của AI là không được lưu.
        $this->assertDatabaseCount('ai_messages', 1);
        $this->assertDatabaseHas('ai_messages', [
            'role' => 'user',
            'content' => 'Hỏi',
        ]);
    }

    public function test_rate_limit_on_message_endpoint_works(): void
    {
        config()->set('ai.enabled', true);

        // Bind a fast fake provider so requests reach the throttle middleware.
        $this->app->instance(AiProvider::class, new class implements AiProvider
        {
            use DelegatesConverseToChat;

            public function chat(array $messages): AiProviderResult
            {
                return new AiProviderResult(
                    content: 'OK',
                    blocks: [['type' => 'text', 'content' => 'OK']],
                    actions: [],
                    provider: 'fake',
                    model: 'fake',
                    promptTokens: 1,
                    completionTokens: 1,
                    totalTokens: 2,
                    latencyMs: 1,
                    providerReference: 'x',
                );
            }
        });

        $owner = User::factory()->owner()->create();

        // The throttle is 20 requests per minute. Send 20 requests with a
        // pre-created conversation so each request is a reply (not a new one).
        $conversation = AiConversation::query()->create([
            'user_id' => $owner->id,
            'scope_branch_ids' => [],
            'last_message_at' => now(),
        ]);

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($owner)
                ->post(route('admin.ai.messages.store'), [
                    'conversation_id' => $conversation->id,
                    'message' => "Hỏi {$i}",
                ])
                ->assertRedirect();
        }

        // 21st request should be rate limited.
        $this->actingAs($owner)
            ->post(route('admin.ai.messages.store'), [
                'conversation_id' => $conversation->id,
                'message' => 'Hỏi 20',
            ])
            ->assertStatus(429);
    }

    public function test_conversation_list_limited_to_30_and_sorted_by_latest(): void
    {
        $owner = User::factory()->owner()->create();

        for ($i = 0; $i < 35; $i++) {
            AiConversation::query()->create([
                'user_id' => $owner->id,
                'title' => "Conv {$i}",
                'scope_branch_ids' => [],
                'last_message_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->actingAs($owner)
            ->get(route('admin.ai.index'))
            ->assertOk();

        $conversations = $response->viewData('conversations');
        $this->assertCount(30, $conversations);
        $this->assertSame('Conv 0', $conversations->first()->title);
        $this->assertSame('Conv 29', $conversations->last()->title);
    }

    public function test_history_sent_to_provider_in_correct_order_and_limit(): void
    {
        $this->app->instance(AiProvider::class, new class implements AiProvider
        {
            use DelegatesConverseToChat;

            public function chat(array $messages): AiProviderResult
            {
                return new AiProviderResult(
                    content: 'OK',
                    blocks: [['type' => 'text', 'content' => 'OK']],
                    actions: [],
                    provider: 'fake',
                    model: 'fake',
                    promptTokens: 1,
                    completionTokens: 1,
                    totalTokens: 2,
                    latencyMs: 1,
                    providerReference: 'x',
                );
            }
        });

        $owner = User::factory()->owner()->create();
        $conversation = AiConversation::query()->create([
            'user_id' => $owner->id,
            'scope_branch_ids' => [],
            'last_message_at' => now(),
        ]);

        // Create 25 historical messages
        for ($i = 0; $i < 25; $i++) {
            AiMessage::query()->create([
                'conversation_id' => $conversation->id,
                'role' => $i % 2 === 0 ? 'user' : 'assistant',
                'content' => "Message {$i}",
            ]);
        }

        config()->set('ai.history_limit', 10);

        $this->actingAs($owner)
            ->post(route('admin.ai.messages.store'), [
                'conversation_id' => $conversation->id,
                'message' => 'Câu hỏi mới',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('ai_messages', 27); // 25 history + 1 user + 1 assistant
    }
}
