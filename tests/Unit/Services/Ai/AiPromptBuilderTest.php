<?php

namespace Tests\Unit\Services\Ai;

use App\Models\AiConversation;
use App\Models\Branch;
use App\Models\User;
use App\Services\Ai\AiBusinessContext;
use App\Services\Ai\AiPromptBuilder;
use App\Services\ReportService;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class AiPromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Session::forget('admin.current_branch_id');
    }

    public function test_the_system_prompt_bans_technical_field_names_in_anything_the_user_reads(): void
    {
        $system = $this->systemPrompt('tạo lịch hẹn cho khách');

        $this->assertStringContainsString('NGÔN NGỮ HIỂN THỊ', $system);
        $this->assertStringContainsString('Cấm nhắc tên trường', $system);
        $this->assertStringContainsString('Tên trường dưới đây chỉ dùng bên trong payload', $system);
        $this->assertStringContainsString('ví dụ: chị Lan, 0901 234 567', $system);
    }

    public function test_the_system_prompt_pairs_every_enum_key_with_a_vietnamese_label(): void
    {
        $system = $this->systemPrompt('ghi khoản chi marketing');

        // Thiếu cặp "khóa = nhãn" thì model chỉ có khóa để nói, và người dùng
        // đọc được "status: pending" đúng như lỗi đã gặp.
        $this->assertStringContainsString('pending = Chờ xác nhận', $system);
        $this->assertStringContainsString('no_show = Không đến', $system);
        $this->assertStringContainsString('khóa = nhãn để nói', $system);
    }

    private function systemPrompt(string $question): string
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();

        $request = Request::create('/ai-prompt');
        $request->setLaravelSession(Session::driver());
        $request->session()->put(BranchContext::SESSION_KEY, $branch->id);

        app()->bind('request', fn () => $request);
        app()->forgetInstance(BranchContext::class);

        $branchContext = new BranchContext($request);
        $branchContext->resolve($owner);
        app()->instance(BranchContext::class, $branchContext);

        $conversation = AiConversation::query()->create([
            'user_id' => $owner->id,
            'branch_id' => $branch->id,
            'scope_branch_ids' => [$branch->id],
            'last_message_at' => now(),
        ]);

        $builder = new AiPromptBuilder(new AiBusinessContext($branchContext, app(ReportService::class)));

        return $builder->build($owner, $conversation, $question)[0]['content'];
    }
}
