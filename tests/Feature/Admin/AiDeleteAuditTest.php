<?php

namespace Tests\Feature\Admin;

use App\Enums\AiActionStatus;
use App\Models\AiActionProposal;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AttendanceRecord;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Ai\Actions\ActionRegistry;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Xóa gì cũng phải để lại bản chụp.
 *
 * Với thao tác hủy mềm thì bản ghi vẫn nằm đó nên tra lại lúc nào cũng được.
 * Với xóa cứng thì dòng audit là bản sao duy nhất còn lại, nên nó phải chứa
 * đủ nội dung của thứ vừa mất chứ không chỉ mỗi cái ID.
 */
class AiDeleteAuditTest extends TestCase
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

    public function test_deleting_an_attendance_row_keeps_a_full_snapshot(): void
    {
        $employee = User::factory()->employee()->atBranch($this->branch)->create();
        $record = AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'shift_name' => 'Ca sáng',
            'work_date' => now()->subDay()->toDateString(),
        ]);
        $proposal = $this->proposal('delete_attendance', ['attendance_id' => $record->id]);

        $this->confirm($proposal, [
            'attendance_id' => $record->id,
            'reason' => 'Ghi trùng hai lần cùng một ca',
        ])->assertSessionHas('success');

        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);

        $event = AuditEvent::query()
            ->where('auditable_type', AiActionProposal::class)
            ->where('auditable_id', $proposal->id)
            ->where('action', 'deleted')
            ->firstOrFail();

        // Nội dung dòng đã mất còn nguyên trong log.
        $this->assertSame('Ca sáng', $event->before['shift_name']);
        $this->assertSame($employee->id, (int) $event->before['employee_id']);
        $this->assertSame($this->owner->id, $event->actor_id);
        $this->assertStringContainsString('Xóa ca công', (string) $event->reason);
    }

    public function test_removing_a_shift_assignment_keeps_a_full_snapshot(): void
    {
        $assignment = $this->assignment();
        $proposal = $this->proposal('remove_shift_assignment', ['shift_assignment_id' => $assignment->id]);

        $this->confirm($proposal, [
            'shift_assignment_id' => $assignment->id,
            'reason' => 'Phân nhầm người',
        ])->assertSessionHas('success');

        $this->assertDatabaseMissing('shift_assignments', ['id' => $assignment->id]);

        // Hai dòng: một của chính action phân ca, một của lớp duyệt đề xuất.
        $shiftEvent = AuditEvent::query()
            ->where('auditable_type', ShiftAssignment::class)
            ->where('auditable_id', $assignment->id)
            ->where('action', 'deleted')
            ->firstOrFail();
        $this->assertSame($assignment->shift_name, $shiftEvent->before['shift_name']);
        $this->assertSame('Phân nhầm người', $shiftEvent->reason);

        $proposalEvent = AuditEvent::query()
            ->where('auditable_type', AiActionProposal::class)
            ->where('auditable_id', $proposal->id)
            ->where('action', 'deleted')
            ->firstOrFail();
        $this->assertSame($assignment->id, (int) $proposalEvent->before['id']);
    }

    /**
     * Bỏ phân ca từ màn hình quản trị cũng phải để lại dấu vết như khi làm qua
     * trợ lý — trước đây đường này xóa thẳng, không ghi gì cả.
     */
    public function test_removing_a_shift_assignment_from_the_admin_screen_is_also_logged(): void
    {
        $assignment = $this->assignment();

        $this->actingAs($this->owner)
            ->withSession(['admin.current_branch_id' => $this->branch->id])
            ->delete(route('admin.shift-schedule.destroy', $assignment))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('shift_assignments', ['id' => $assignment->id]);
        $this->assertDatabaseHas('audit_events', [
            'auditable_type' => ShiftAssignment::class,
            'auditable_id' => $assignment->id,
            'action' => 'deleted',
            'actor_id' => $this->owner->id,
        ]);
    }

    /** Mọi thao tác xóa đều phải đi qua chỗ có ghi log, không có ngoại lệ. */
    public function test_every_destructive_action_is_marked_as_a_delete(): void
    {
        $destructive = array_filter(
            app(ActionRegistry::class)->all(),
            fn ($action): bool => $action->destructive,
        );

        $this->assertNotEmpty($destructive);

        foreach ($destructive as $key => $action) {
            $this->assertSame('delete', $action->operation, "Thao tác {$key} đánh dấu là phá hủy nhưng không khai là xóa.");
        }
    }

    private function assignment(): ShiftAssignment
    {
        $employee = User::factory()->employee()->atBranch($this->branch)->create();

        return ShiftAssignment::factory()->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'work_date' => now()->addDay()->toDateString(),
        ]);
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
