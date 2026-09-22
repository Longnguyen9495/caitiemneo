<?php

namespace Tests\Unit\Services\Ai;

use App\Enums\AiActionStatus;
use App\Enums\AppointmentStatus;
use App\Models\AiActionProposal;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Branch;
use App\Models\User;
use App\Services\Ai\AiActionFormBuilder;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiActionFormBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Session::forget('admin.current_branch_id');
        app()->forgetInstance(BranchContext::class);
    }

    public function test_every_action_type_offers_its_required_fields(): void
    {
        $builder = app(AiActionFormBuilder::class);

        $expected = [
            'create_appointment' => ['customer_name', 'customer_phone', 'starts_at', 'duration_minutes', 'status'],
            'update_appointment' => ['appointment_id', 'customer_name', 'starts_at', 'status'],
            'cancel_appointment' => ['appointment_id', 'reason'],
            'create_cash_entry' => ['type', 'category', 'amount', 'occurred_at'],
            'adjust_stock' => ['product_id', 'adjustment_mode', 'quantity', 'occurred_at', 'note', 'type'],
        ];

        foreach ($expected as $type => $keys) {
            $this->assertSame($keys, array_values(array_intersect($builder->editableKeys($type), $keys)), $type);
        }
    }

    public function test_an_unknown_action_type_offers_nothing_to_fill_in(): void
    {
        $this->assertSame([], app(AiActionFormBuilder::class)->editableKeys('drop_database'));
    }

    public function test_the_form_keeps_what_the_assistant_filled_in_and_defaults_the_rest(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();
        $this->actingAs($owner);
        Session::put('admin.current_branch_id', $branch->id);

        $proposal = $this->proposal($owner, $branch, [
            'branch_id' => $branch->id,
            'customer_name' => 'Chị Lan',
            'starts_at' => '2026-10-01T09:30:00+07:00',
        ]);

        $fields = collect(app(AiActionFormBuilder::class)->fields($proposal))->keyBy('key');

        $this->assertSame('Chị Lan', $fields['customer_name']['value']);
        $this->assertSame('2026-10-01T09:30', $fields['starts_at']['value']);
        // Trợ lý không nói gì thì phiếu mở bằng mặc định của tiệm, không để trống.
        $this->assertSame(60, $fields['duration_minutes']['value']);
        $this->assertSame(AppointmentStatus::Pending->value, $fields['status']['value']);
        $this->assertNull($fields['customer_phone']['value']);
        $this->assertTrue($fields['customer_phone']['required']);
        $this->assertFalse($fields['customer_email']['required']);
    }

    /** @param array<string, mixed> $payload */
    private function proposal(User $owner, Branch $branch, array $payload): AiActionProposal
    {
        $conversation = AiConversation::query()->create([
            'user_id' => $owner->id,
            'branch_id' => $branch->id,
            'scope_branch_ids' => [$branch->id],
            'last_message_at' => now(),
        ]);
        $message = AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Đề xuất tạo lịch hẹn.',
        ]);

        return AiActionProposal::query()->create([
            'message_id' => $message->id,
            'proposed_by' => $owner->id,
            'branch_id' => $branch->id,
            'type' => 'create_appointment',
            'summary' => 'Tạo lịch hẹn',
            'payload' => $payload,
            'status' => AiActionStatus::Pending,
            'idempotency_key' => (string) Str::uuid(),
            'request_fingerprint' => hash('sha256', Str::uuid()->toString()),
        ]);
    }
}
