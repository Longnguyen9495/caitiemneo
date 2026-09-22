<?php

namespace Tests\Unit\Services\Ai;

use App\Models\AiConversation;
use App\Models\Branch;
use App\Models\User;
use App\Services\Ai\Actions\ActionRegistry;
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
        $this->assertStringContainsString('marketing = Marketing', $system);
    }

    /**
     * Danh sách thao tác sinh từ bản khai chứ không chép tay, nên thêm một đối
     * tượng mới là model biết ngay. Test này giữ đúng điều đó: nếu ai sinh lại
     * prompt bằng văn bản cứng, những thao tác mới sẽ biến mất khỏi đây.
     */
    public function test_the_system_prompt_lists_every_registered_action(): void
    {
        $system = $this->systemPrompt('sửa dịch vụ đắp gel');

        foreach (app(ActionRegistry::class)->keys() as $key) {
            $this->assertStringContainsString($key, $system, $key);
        }

        $this->assertStringContainsString('create_service — Tạo dịch vụ', $system);
        $this->assertStringContainsString('cancel_invoice — Hủy hóa đơn', $system);
    }

    /**
     * Hai luật này kéo nhau: bỏ luật mở phiếu thì trợ lý quay lại hỏi vặn từng
     * trường, bỏ luật dữ liệu thử thì phiếu làm mẫu mở ra trống trơn.
     */
    public function test_the_system_prompt_opens_a_form_instead_of_interrogating(): void
    {
        $system = $this->systemPrompt('tạo lịch hẹn test cho mình');

        $this->assertStringContainsString('THIẾU THÔNG TIN THÌ VẪN MỞ PHIẾU', $system);
        $this->assertStringContainsString('kể cả khi còn thiếu trường bắt buộc', $system);
        $this->assertStringContainsString('Đừng hỏi lại từng trường', $system);

        $this->assertStringContainsString('DỮ LIỆU THỬ THÌ TỰ ĐIỀN CHO ĐẦY', $system);
        $this->assertStringContainsString('chỉ việc bấm duyệt một lần', $system);
        // Tự điền đến mấy cũng không được tự ý ghi vào sổ.
        $this->assertStringContainsString('vẫn phải chờ người dùng bấm duyệt', $system);
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

        $builder = new AiPromptBuilder(
            new AiBusinessContext($branchContext, app(ReportService::class)),
            app(ActionRegistry::class),
        );

        return $builder->build($owner, $conversation, $question)[0]['content'];
    }
}
