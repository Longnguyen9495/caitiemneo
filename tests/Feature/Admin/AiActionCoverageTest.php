<?php

namespace Tests\Feature\Admin;

use App\Enums\AiActionStatus;
use App\Models\AiActionProposal;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\Ai\Actions\ActionRegistry;
use App\Services\Ai\AiActionFormBuilder;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Phiếu xác nhận cho những đối tượng ngoài lịch hẹn và thu chi.
 *
 * Phần khung đã có test riêng; ở đây kiểm đúng chỗ dễ sai khi khai thêm một
 * đối tượng mới: phiếu vẽ ra được, dữ liệu gửi lên chạy thật, và cái gì không
 * khai thì không làm được.
 */
class AiActionCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.enabled', true);
        RateLimiter::clear('admin.ai.actions.confirm');
        Session::forget('admin.current_branch_id');
        app()->forgetInstance(BranchContext::class);

        $this->branch = Branch::factory()->create();
        $this->owner = User::factory()->owner()->atBranch($this->branch)->create();
    }

    public function test_every_registered_action_can_draw_its_form(): void
    {
        Session::put('admin.current_branch_id', $this->branch->id);
        $this->actingAs($this->owner);
        app(BranchContext::class)->resolve($this->owner);

        foreach (app(ActionRegistry::class)->all() as $key => $action) {
            $proposal = $this->proposal($key, ['branch_id' => $this->branch->id]);
            $fields = app(AiActionFormBuilder::class)->fields($proposal);

            $this->assertNotEmpty($fields, "Thao tác {$key} không có ô nhập nào.");

            foreach ($fields as $field) {
                $this->assertNotSame('', $field['label'], "Ô {$field['key']} của {$key} thiếu nhãn.");
            }
        }
    }

    public function test_creating_a_service_from_the_form(): void
    {
        $proposal = $this->proposal('create_service', []);

        $this->confirm($proposal, [
            'name' => 'Đắp gel hoa nổi',
            'category' => 'basic_nail',
            'unit' => 'set',
            'price' => '350000',
            'display_order' => '1',
            'is_active' => '1',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('services', ['name' => 'Đắp gel hoa nổi']);
        $this->assertSame(AiActionStatus::Executed, $proposal->fresh()->status);
    }

    public function test_updating_a_product_from_the_form(): void
    {
        $product = Product::factory()->create(['name' => 'Tên cũ', 'is_active' => true]);
        $proposal = $this->proposal('update_product', ['product_id' => $product->id]);

        $this->confirm($proposal, [
            'product_id' => $product->id,
            'name' => 'Nước rửa gel',
            'unit' => 'chai',
            'cost_price' => '85000',
            'minimum_stock' => '5',
            'is_active' => '1',
        ])->assertSessionHas('success');

        $this->assertSame('Nước rửa gel', $product->fresh()->name);
    }

    public function test_creating_a_supplier_and_a_work_shift_from_the_form(): void
    {
        $this->confirm($this->proposal('create_supplier', []), [
            'name' => 'Công ty vật tư ABC',
            'phone' => '0901234567',
            'is_active' => '1',
        ])->assertSessionHas('success');

        $this->confirm($this->proposal('create_work_shift', []), [
            'name' => 'Ca tối',
            'starts_at' => '17:00',
            'ends_at' => '22:00',
            'shift_value' => '1',
            'grace_minutes' => '10',
            'is_active' => '1',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('suppliers', ['name' => 'Công ty vật tư ABC']);
        $this->assertDatabaseHas('work_shifts', ['name' => 'Ca tối']);
    }

    public function test_a_service_cannot_be_updated_with_an_id_that_does_not_exist(): void
    {
        Service::factory()->create();
        $proposal = $this->proposal('update_service', ['service_id' => 9999]);

        $this->confirm($proposal, [
            'service_id' => 9999,
            'name' => 'Không tồn tại',
            'category' => 'basic_nail',
            'unit' => 'set',
            'price' => '100000',
        ])->assertSessionHasErrors(['payload.service_id']);

        $this->assertSame(AiActionStatus::Pending, $proposal->fresh()->status);
    }

    /**
     * Ba nhóm bị loại khỏi phạm vi phải thực sự không có đường vào, chứ không
     * chỉ là không được nhắc tới trong prompt.
     *
     * "employee" không nằm trong danh sách cấm vì vài thao tác hợp lệ có nhắc
     * tới nhân viên — phân ca, gán người thực hiện cho hóa đơn — mà không hề
     * chạm vào tài khoản hay quyền của họ.
     */
    public function test_sensitive_groups_have_no_action_at_all(): void
    {
        $keys = app(ActionRegistry::class)->keys();

        foreach (['user', 'payroll', 'branch', 'role', 'password'] as $forbidden) {
            $matches = array_values(array_filter(
                $keys,
                fn (string $key): bool => str_contains($key, $forbidden),
            ));

            $this->assertSame([], $matches, "Không được có thao tác chạm tới {$forbidden}: ".implode(', ', $matches));
        }
    }

    /** @param array<string, mixed> $payload */
    private function confirm(AiActionProposal $proposal, array $payload): TestResponse
    {
        return $this->actingAs($this->owner)
            ->withSession(['admin.current_branch_id' => $this->branch->id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal), [
                'proposal_id' => $proposal->id,
                'payload' => $payload,
            ]);
    }

    /** @param array<string, mixed> $payload */
    private function proposal(string $type, array $payload): AiActionProposal
    {
        $conversation = AiConversation::query()->create([
            'user_id' => $this->owner->id,
            'branch_id' => $this->branch->id,
            'scope_branch_ids' => [$this->branch->id],
            'last_message_at' => now(),
        ]);
        $message = AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Đề xuất thao tác.',
        ]);

        return AiActionProposal::query()->create([
            'message_id' => $message->id,
            'proposed_by' => $this->owner->id,
            'branch_id' => $this->branch->id,
            'type' => $type,
            'summary' => 'Đề xuất '.$type,
            'payload' => $payload,
            'status' => AiActionStatus::Pending,
            'idempotency_key' => (string) Str::uuid(),
            'request_fingerprint' => hash('sha256', Str::uuid()->toString()),
        ]);
    }
}
