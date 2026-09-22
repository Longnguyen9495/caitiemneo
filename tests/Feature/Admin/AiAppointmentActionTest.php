<?php

namespace Tests\Feature\Admin;

use App\Enums\AiActionStatus;
use App\Enums\AppointmentStatus;
use App\Models\AiActionProposal;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Service;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AiAppointmentActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.enabled', true);
        RateLimiter::clear('admin.ai.actions.confirm');
        RateLimiter::clear('admin.ai.actions.reject');
        Session::forget('admin.current_branch_id');
        app()->forgetInstance(BranchContext::class);
    }

    public function test_create_appointment_waits_for_approval_then_executes_once_with_audit(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();
        $service = Service::factory()->create();
        BranchService::factory()->create([
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'is_active' => true,
        ]);
        $proposal = $this->proposal($owner, $branch, 'create_appointment', [
            'branch_id' => $branch->id,
            'customer_name' => 'Khách AI',
            'customer_phone' => '0901234567',
            'customer_email' => null,
            'employee_id' => null,
            'starts_at' => now()->addDay()->startOfHour()->toIso8601String(),
            'duration_minutes' => 60,
            'status' => AppointmentStatus::Pending->value,
            'service_ids' => [$service->id],
            'note' => 'Tạo từ đề xuất AI',
        ]);

        $this->assertDatabaseCount('appointments', 0);
        $this->assertSame(AiActionStatus::Pending, $proposal->status);

        $this->confirm($owner, $branch, $proposal)->assertSessionHas('success');
        $this->confirm($owner, $branch, $proposal)->assertRedirect();

        $this->assertDatabaseCount('appointments', 1);
        $appointment = Appointment::query()->firstOrFail();
        $fresh = $proposal->fresh();
        $this->assertSame(AiActionStatus::Executed, $fresh->status);
        $this->assertSame($appointment->id, $fresh->result_id);
        $this->assertDatabaseHas('audit_events', [
            'auditable_type' => Appointment::class,
            'auditable_id' => $appointment->id,
            'action' => 'created',
            'actor_id' => $owner->id,
            'branch_id' => $branch->id,
        ]);
        $this->assertNotNull(AuditEvent::query()->where('auditable_id', $appointment->id)->firstOrFail()->after);
    }

    public function test_update_appointment_requires_approval_and_records_before_after(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();
        $startsAt = now()->addDays(2)->startOfHour();
        $appointment = Appointment::factory()->at($startsAt)->create([
            'branch_id' => $branch->id,
            'customer_name' => 'Tên cũ',
            'customer_phone' => '0900000000',
        ]);
        $proposal = $this->proposal($owner, $branch, 'update_appointment', [
            'appointment_id' => $appointment->id,
            'branch_id' => $branch->id,
            'customer_name' => 'Tên mới',
            'customer_phone' => '0900000000',
            'customer_email' => null,
            'employee_id' => null,
            'starts_at' => $startsAt->addHour()->toIso8601String(),
            'duration_minutes' => 90,
            'status' => AppointmentStatus::Confirmed->value,
            'service_ids' => [],
            'note' => 'Dời lịch theo yêu cầu',
        ]);

        $this->assertSame('Tên cũ', $appointment->fresh()->customer_name);
        $this->confirm($owner, $branch, $proposal)->assertSessionHas('success');

        $this->assertSame('Tên mới', $appointment->fresh()->customer_name);
        $event = AuditEvent::query()
            ->where('auditable_type', Appointment::class)
            ->where('auditable_id', $appointment->id)
            ->where('action', 'updated')
            ->firstOrFail();
        $this->assertSame('Tên cũ', $event->before['customer_name']);
        $this->assertSame('Tên mới', $event->after['customer_name']);
        $this->assertSame($owner->id, $event->actor_id);
    }

    public function test_cancel_appointment_is_soft_delete_after_approval_and_is_audited(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();
        $appointment = Appointment::factory()->create([
            'branch_id' => $branch->id,
            'status' => AppointmentStatus::Confirmed,
        ]);
        $proposal = $this->proposal($owner, $branch, 'cancel_appointment', [
            'branch_id' => $branch->id,
            'appointment_id' => $appointment->id,
            'reason' => 'Khách yêu cầu hủy lịch',
        ]);

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
        $this->confirm($owner, $branch, $proposal)->assertSessionHas('success');

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $event = AuditEvent::query()
            ->where('auditable_type', Appointment::class)
            ->where('auditable_id', $appointment->id)
            ->where('action', 'cancelled')
            ->firstOrFail();
        $this->assertSame(AppointmentStatus::Confirmed->value, $event->before['status']);
        $this->assertSame(AppointmentStatus::Cancelled->value, $event->after['status']);
        $this->assertSame('Khách yêu cầu hủy lịch', $event->reason);
    }

    public function test_a_decided_proposal_describes_the_payload_without_technical_field_names(): void
    {
        $branch = Branch::factory()->create(['name' => 'Cái Tiệm Neo Thái Hà']);
        $owner = User::factory()->owner()->atBranch($branch)->create();
        $proposal = $this->proposal($owner, $branch, 'create_appointment', [
            'branch_id' => $branch->id,
            'customer_name' => 'Chị Lan',
            'customer_phone' => '0901234567',
            'customer_email' => null,
            'employee_id' => null,
            'starts_at' => now()->addDay()->startOfHour()->toIso8601String(),
            'duration_minutes' => 60,
            'status' => AppointmentStatus::Pending->value,
            'service_ids' => [],
            'note' => 'Khách quen',
        ]);
        $proposal->forceFill(['status' => AiActionStatus::Rejected])->save();

        $response = $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $branch->id])
            ->get(route('admin.ai.index', ['conversation' => $proposal->message->conversation_id]));

        $response->assertOk()
            // Nhãn giống hệt phiếu nhập, vì cả hai giờ đọc từ một bản khai.
            ->assertSee('Tên khách')
            ->assertSee('Chị Lan')
            ->assertSee('Thời lượng')
            ->assertSee('Chờ xác nhận')
            ->assertSee('Cái Tiệm Neo Thái Hà');

        foreach (['starts_at', 'Starts At', 'customer_name', 'Customer Name', 'Branch Id', 'duration_minutes'] as $technicalToken) {
            $response->assertDontSee($technicalToken);
        }
    }

    /**
     * Phiếu chờ duyệt là chỗ người dùng sửa dữ liệu, nên ô nhập buộc phải mang
     * tên trường thật. Ràng buộc "không lộ tên kỹ thuật" vì thế chỉ áp cho phần
     * chữ người đọc: nhãn, gợi ý và câu dẫn.
     */
    public function test_a_pending_proposal_opens_an_editable_form_with_vietnamese_labels(): void
    {
        $branch = Branch::factory()->create(['name' => 'Cái Tiệm Neo Thái Hà']);
        $owner = User::factory()->owner()->atBranch($branch)->create();
        $proposal = $this->proposal($owner, $branch, 'create_appointment', [
            'branch_id' => $branch->id,
            'customer_name' => 'Chị Lan',
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $branch->id])
            ->get(route('admin.ai.index', ['conversation' => $proposal->message->conversation_id]));

        $response->assertOk()
            ->assertSee('Tên khách')
            ->assertSee('Giờ hẹn')
            ->assertSee('Thời lượng (phút)')
            ->assertSee('Duyệt và thực hiện')
            ->assertSee('Cái Tiệm Neo Thái Hà')
            ->assertSee('value="Chị Lan"', escape: false)
            ->assertSee('name="payload[customer_phone]"', escape: false);

        foreach (['Starts At', 'Customer Name', 'Branch Id', 'Duration Minutes'] as $humanisedToken) {
            $response->assertDontSee($humanisedToken);
        }
    }

    public function test_an_incomplete_draft_is_completed_from_the_form_and_then_executes(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();
        $startsAt = now()->addDay()->startOfHour();

        // Trợ lý chỉ nghe được mỗi "tạo lịch hẹn test": phiếu mở ra gần như trống.
        $proposal = $this->proposal($owner, $branch, 'create_appointment', [
            'branch_id' => $branch->id,
        ]);

        $this->confirm($owner, $branch, $proposal, [
            'branch_id' => $branch->id,
            'customer_name' => 'Chị Test',
            'customer_phone' => '0909999999',
            'customer_email' => '',
            'employee_id' => '',
            'starts_at' => $startsAt->format('Y-m-d\TH:i'),
            'duration_minutes' => '30',
            'status' => AppointmentStatus::Pending->value,
            'note' => '',
        ])->assertSessionHas('success');

        $appointment = Appointment::query()->firstOrFail();
        $this->assertSame('Chị Test', $appointment->customer_name);
        $this->assertSame('0909999999', $appointment->customer_phone);
        $this->assertSame(30, $appointment->duration_minutes);
        $this->assertNull($appointment->employee_id);

        $fresh = $proposal->fresh();
        $this->assertSame(AiActionStatus::Executed, $fresh->status);
        $this->assertSame('Chị Test', $fresh->payload['customer_name']);
        $this->assertDatabaseHas('audit_events', [
            'auditable_type' => AiActionProposal::class,
            'auditable_id' => $proposal->id,
            'action' => 'updated',
            'actor_id' => $owner->id,
        ]);
    }

    public function test_the_approver_can_correct_what_the_assistant_got_wrong(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();
        $startsAt = now()->addDay()->startOfHour();
        $proposal = $this->proposal($owner, $branch, 'create_appointment', [
            'branch_id' => $branch->id,
            'customer_name' => 'Nghe nhầm tên',
            'customer_phone' => '0901234567',
            'starts_at' => $startsAt->toIso8601String(),
            'duration_minutes' => 60,
            'status' => AppointmentStatus::Pending->value,
        ]);

        $this->confirm($owner, $branch, $proposal, [
            'branch_id' => $branch->id,
            'customer_name' => 'Chị Lan',
            'customer_phone' => '0901234567',
            'starts_at' => $startsAt->format('Y-m-d\TH:i'),
            'duration_minutes' => '60',
            'status' => AppointmentStatus::Confirmed->value,
        ])->assertSessionHas('success');

        $appointment = Appointment::query()->firstOrFail();
        $this->assertSame('Chị Lan', $appointment->customer_name);
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);

        $edit = AuditEvent::query()
            ->where('auditable_type', AiActionProposal::class)
            ->where('auditable_id', $proposal->id)
            ->firstOrFail();
        $this->assertSame('Nghe nhầm tên', $edit->before['customer_name']);
        $this->assertSame('Chị Lan', $edit->after['customer_name']);
    }

    public function test_a_form_still_missing_a_required_field_changes_nothing(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();
        $proposal = $this->proposal($owner, $branch, 'create_appointment', [
            'branch_id' => $branch->id,
        ]);

        $this->confirm($owner, $branch, $proposal, [
            'branch_id' => $branch->id,
            'customer_name' => 'Chị Test',
            'customer_phone' => '',
            'starts_at' => '',
            'duration_minutes' => '30',
            'status' => AppointmentStatus::Pending->value,
        ])
            ->assertSessionHasErrors(['payload.customer_phone', 'payload.starts_at'])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('appointments', 0);
        $this->assertSame(AiActionStatus::Pending, $proposal->fresh()->status);
    }

    public function test_the_form_cannot_smuggle_in_a_field_the_approver_never_saw(): void
    {
        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();
        $owner = User::factory()->owner()->atBranch($branch)->create();
        $startsAt = now()->addDay()->startOfHour();
        $proposal = $this->proposal($owner, $branch, 'cancel_appointment', [
            'branch_id' => $branch->id,
        ]);
        $appointment = Appointment::factory()->at($startsAt)->create([
            'branch_id' => $branch->id,
            'status' => AppointmentStatus::Confirmed,
        ]);

        $this->confirm($owner, $branch, $proposal, [
            'branch_id' => $branch->id,
            'appointment_id' => $appointment->id,
            'reason' => 'Khách báo bận',
            // Không có ô nào tên như vậy trên phiếu hủy lịch.
            'customer_name' => 'Đổi trộm tên',
            'status' => AppointmentStatus::Completed->value,
        ])->assertSessionHas('success');

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertArrayNotHasKey('customer_name', $proposal->fresh()->payload);
        $this->assertArrayNotHasKey('status', $proposal->fresh()->payload);
    }

    /** @param array<string, mixed>|null $payload */
    private function confirm(User $owner, Branch $branch, AiActionProposal $proposal, ?array $payload = null): TestResponse
    {
        return $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $branch->id])
            ->withConfirmedPassword()
            ->post(
                route('admin.ai.actions.confirm', $proposal),
                $payload === null ? [] : ['payload' => $payload, 'proposal_id' => $proposal->id],
            );
    }

    /** @param array<string, mixed> $payload */
    private function proposal(User $owner, Branch $branch, string $type, array $payload): AiActionProposal
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
            'content' => 'Đề xuất thao tác lịch hẹn.',
        ]);

        return AiActionProposal::query()->create([
            'message_id' => $message->id,
            'proposed_by' => $owner->id,
            'branch_id' => $branch->id,
            'type' => $type,
            'summary' => 'Đề xuất thao tác lịch hẹn',
            'payload' => $payload,
            'status' => AiActionStatus::Pending,
            'idempotency_key' => (string) Str::uuid(),
            'request_fingerprint' => hash('sha256', Str::uuid()->toString()),
        ]);
    }
}
