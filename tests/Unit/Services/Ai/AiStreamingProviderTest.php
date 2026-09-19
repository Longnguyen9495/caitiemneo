<?php

namespace Tests\Unit\Services\Ai;

use App\Models\Branch;
use App\Models\User;
use App\Services\Ai\AiActionCatalog;
use App\Services\Ai\AiStreamEvent;
use App\Services\Ai\AiToolExecutor;
use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\OpenAiCompatibleProvider;
use App\Services\ReportService;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class AiStreamingProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ai.enabled', true);
        Config::set('ai.base_url', 'https://ai.example.com/v1');
        Config::set('ai.api_key', 'test-key');
        Config::set('ai.model', 'test-model');
        Config::set('ai.temperature', 0.2);
        Config::set('ai.max_output_tokens', 3000);
        Config::set('ai.connect_timeout', 10);
        Config::set('ai.timeout', 60);
        Session::forget('admin.current_branch_id');
    }

    /** @param array<int, array<string, mixed>> $deltas */
    private function sse(array $deltas): string
    {
        $body = '';

        foreach ($deltas as $delta) {
            $body .= 'data: '.json_encode($delta, JSON_UNESCAPED_UNICODE)."\n\n";
        }

        return $body."data: [DONE]\n\n";
    }

    private function provider(): OpenAiCompatibleProvider
    {
        $request = Request::create('/ai-stream');
        $request->setLaravelSession(Session::driver());
        $request->session()->put(BranchContext::SESSION_KEY, BranchContext::ALL);
        app()->bind('request', fn () => $request);
        app()->forgetInstance(BranchContext::class);

        $user = $this->owner;
        $branchContext = new BranchContext($request);
        $branchContext->resolve($user);
        app()->instance(BranchContext::class, $branchContext);

        return new OpenAiCompatibleProvider(
            new AiActionCatalog,
            new AiToolRegistry,
            new AiToolExecutor($branchContext, app(ReportService::class), new AiToolRegistry),
        );
    }

    private User $owner;

    private function makeOwner(): User
    {
        Branch::factory()->create(['is_active' => true]);
        $this->owner = User::factory()->owner()->create();

        return $this->owner;
    }

    public function test_streamed_text_is_assembled_and_emitted_progressively(): void
    {
        $user = $this->makeOwner();

        $document = json_encode([
            'content' => 'Doanh thu hôm nay là 5 triệu đồng.',
            'blocks' => [],
            'actions' => [],
        ], JSON_UNESCAPED_UNICODE);

        // Cắt tài liệu thành nhiều mẩu để mô phỏng đúng cách provider nhỏ giọt
        // từng phần chứ không trả về trọn vẹn một lần.
        $chunks = str_split($document, 20);
        $deltas = array_map(
            fn (string $piece): array => ['id' => 'resp-1', 'choices' => [['delta' => ['content' => $piece]]]],
            $chunks,
        );

        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response($this->sse($deltas)),
        ]);

        $seen = [];
        $result = $this->provider()->converse(
            [['role' => 'user', 'content' => 'doanh thu hôm nay']],
            $user,
            function (AiStreamEvent $event) use (&$seen): void {
                $seen[] = $event;
            },
        );

        $this->assertSame('Doanh thu hôm nay là 5 triệu đồng.', $result->content);

        // Nhiều sự kiện chứ không phải một: đó chính là điều khiến giao diện vẽ
        // dần thay vì đứng im tới lúc xong.
        $this->assertGreaterThan(1, count($seen));
        $this->assertSame(AiStreamEvent::Delta, $seen[0]->type);
    }

    public function test_a_tool_call_split_across_chunks_is_reassembled_and_executed(): void
    {
        $user = $this->makeOwner();

        $arguments = json_encode(['keyword' => 'Lan']);
        $half = (int) ceil(strlen($arguments) / 2);

        $firstTurn = $this->sse([
            ['id' => 'resp-1', 'choices' => [['delta' => ['tool_calls' => [[
                'index' => 0,
                'id' => 'call_1',
                'function' => ['name' => 'find_appointment', 'arguments' => substr($arguments, 0, $half)],
            ]]]]]],
            // Phần đuôi tham số tới ở chunk sau; ghép sai là JSON hỏng và công cụ
            // chạy với tham số rỗng.
            ['id' => 'resp-1', 'choices' => [['delta' => ['tool_calls' => [[
                'index' => 0,
                'function' => ['arguments' => substr($arguments, $half)],
            ]]]]]],
        ]);

        $secondTurn = $this->sse([
            ['id' => 'resp-2', 'choices' => [['delta' => ['content' => json_encode([
                'content' => 'Không tìm thấy lịch hẹn nào của chị Lan.',
                'blocks' => [],
                'actions' => [],
            ], JSON_UNESCAPED_UNICODE)]]]],
        ]);

        Http::fakeSequence('https://ai.example.com/v1/chat/completions')
            ->push($firstTurn)
            ->push($secondTurn);

        $labels = [];
        $result = $this->provider()->converse(
            [['role' => 'user', 'content' => 'chị Lan có hẹn không']],
            $user,
            function (AiStreamEvent $event) use (&$labels): void {
                if ($event->type === AiStreamEvent::ToolCall) {
                    $labels[] = $event->toolLabel;
                }
            },
        );

        $this->assertSame('Không tìm thấy lịch hẹn nào của chị Lan.', $result->content);
        $this->assertSame([['tool' => 'find_appointment', 'arguments' => ['keyword' => 'Lan']]], $result->toolsUsed);
        $this->assertSame(['Đang tra lịch hẹn…'], $labels);
    }

    public function test_an_event_split_across_read_boundaries_is_still_parsed(): void
    {
        $user = $this->makeOwner();

        // Một sự kiện bị cắt giữa chừng giữa hai lần đọc là chuyện thường gặp
        // trên mạng thật; bộ đệm phải ghép lại được thay vì bỏ qua.
        $document = json_encode([
            'content' => 'Xong.',
            'blocks' => [],
            'actions' => [],
        ], JSON_UNESCAPED_UNICODE);

        $body = 'data: '.json_encode(['id' => 'r', 'choices' => [['delta' => ['content' => $document]]]], JSON_UNESCAPED_UNICODE)."\n\n"
            ."data: [DONE]\n\n";

        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response($body),
        ]);

        $result = $this->provider()->converse([['role' => 'user', 'content' => 'hỏi']], $user);

        $this->assertSame('Xong.', $result->content);
    }

    public function test_usage_totals_are_carried_through_the_stream(): void
    {
        $user = $this->makeOwner();

        $deltas = [
            ['id' => 'resp-1', 'choices' => [['delta' => ['content' => json_encode([
                'content' => 'Xong.',
                'blocks' => [],
                'actions' => [],
            ], JSON_UNESCAPED_UNICODE)]]]],
            ['id' => 'resp-1', 'choices' => [], 'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 30]],
        ];

        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response($this->sse($deltas)),
        ]);

        $result = $this->provider()->converse([['role' => 'user', 'content' => 'hỏi']], $user);

        $this->assertSame(120, $result->promptTokens);
        $this->assertSame(30, $result->completionTokens);
        $this->assertSame(150, $result->totalTokens);
    }
}
