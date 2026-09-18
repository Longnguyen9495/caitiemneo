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

    private function confirm(User $owner, Branch $branch, AiActionProposal $proposal): TestResponse
    {
        return $this->actingAs($owner)
            ->withSession(['admin.current_branch_id' => $branch->id])
            ->withConfirmedPassword()
            ->post(route('admin.ai.actions.confirm', $proposal));
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
