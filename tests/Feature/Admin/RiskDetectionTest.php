<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\AuditAction;
use App\Enums\InvoiceStatus;
use App\Enums\RiskReviewStatus;
use App\Enums\RiskSeverity;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\RiskFlag;
use App\Models\User;
use App\Services\Risk\RiskDetector;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Rules that ask a question about a pattern, and the queue where somebody
 * answers it.
 *
 * Every flag here names a person by implication, so each one has to be
 * explainable in a sentence and each one has to be closeable. A queue that
 * fills with noise is a queue people stop reading, and then it protects
 * nothing at all.
 */
class RiskDetectionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00'));

        $this->branch = Branch::factory()->create();
        $this->owner = User::factory()->owner()->atBranch($this->branch)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_repeated_invoice_cancellations_raise_a_flag(): void
    {
        $this->auditEvents(AuditAction::Cancelled, 3);

        $this->assertGreaterThan(0, app(RiskDetector::class)->sweep(now()->subDays(7)));

        $flag = RiskFlag::query()->where('rule', 'invoice_cancellations')->firstOrFail();

        $this->assertSame(RiskSeverity::High, $flag->severity);
        $this->assertSame($this->owner->getKey(), $flag->actor_id);
        $this->assertStringContainsString('Hủy 3 hóa đơn', $flag->summary);
    }

    /** Below the threshold there is nothing worth asking about. */
    public function test_a_single_cancellation_raises_nothing(): void
    {
        $this->auditEvents(AuditAction::Cancelled, 1);

        app(RiskDetector::class)->sweep(now()->subDays(7));

        $this->assertSame(0, RiskFlag::query()->where('rule', 'invoice_cancellations')->count());
    }

    public function test_money_moved_outside_opening_hours_is_flagged(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 03:30:00'));

        $this->auditEvents(AuditAction::Paid, 1);

        app(RiskDetector::class)->sweep(now()->subDays(7));

        $flag = RiskFlag::query()->where('rule', 'out_of_hours_money')->firstOrFail();

        $this->assertStringContainsString('ngoài giờ mở cửa', $flag->summary);
    }

    public function test_money_moved_during_opening_hours_is_not_flagged(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 14:00:00'));

        $this->auditEvents(AuditAction::Paid, 1);

        app(RiskDetector::class)->sweep(now()->subDays(7));

        $this->assertSame(0, RiskFlag::query()->where('rule', 'out_of_hours_money')->count());
    }

    /** An owner writing their own shift cannot be blocked, so it is flagged. */
    public function test_self_recorded_attendance_is_flagged(): void
    {
        $this->actingAs($this->owner)->post(route('admin.attendance.store'), [
            'employee_id' => $this->owner->getKey(),
            'work_date' => now()->toDateString(),
            'shift_name' => 'Ca chinh',
            'shift_value' => 1,
            'status' => AttendanceStatus::Present->value,
            'reason' => 'Tu ghi ca cua minh',
        ])->assertSessionHasNoErrors();

        app(RiskDetector::class)->sweep(now()->subDays(7));

        $this->assertSame(1, RiskFlag::query()->where('rule', 'self_recorded_attendance')->count());
    }

    /** Running the sweep twice must not double the queue. */
    public function test_sweeping_twice_does_not_duplicate_flags(): void
    {
        $this->auditEvents(AuditAction::Cancelled, 3);

        $detector = app(RiskDetector::class);
        $detector->sweep(now()->subDays(7));
        $detector->sweep(now()->subDays(7));

        $this->assertSame(1, RiskFlag::query()->where('rule', 'invoice_cancellations')->count());
    }

    /** A decision already recorded must survive the next sweep. */
    public function test_a_reviewed_flag_is_not_reopened(): void
    {
        $this->auditEvents(AuditAction::Cancelled, 3);

        $detector = app(RiskDetector::class);
        $detector->sweep(now()->subDays(7));

        RiskFlag::query()->where('rule', 'invoice_cancellations')->update([
            'review_status' => RiskReviewStatus::Dismissed->value,
            'reviewed_by' => $this->owner->getKey(),
            'reviewed_at' => now(),
            'review_note' => 'Khach doi y that, da kiem tra',
        ]);

        $detector->sweep(now()->subDays(7));

        $flag = RiskFlag::query()->where('rule', 'invoice_cancellations')->firstOrFail();

        $this->assertSame(RiskReviewStatus::Dismissed, $flag->review_status);
        $this->assertSame('Khach doi y that, da kiem tra', $flag->review_note);
    }

    /** The queue has to be reachable and show what is open. */
    public function test_leadership_can_read_the_queue(): void
    {
        $this->auditEvents(AuditAction::Cancelled, 3);

        // Owner thấy mọi chi nhánh nên phải nói rõ đang đứng ở chi nhánh nào:
        // nếu không, BranchContext chọn chi nhánh đầu tiên trong DB và hàng đợi
        // rỗng, khiến test xanh mà chẳng kiểm tra gì.
        $this->actingAs($this->owner)
            ->withSession([BranchContext::SESSION_KEY => $this->branch->getKey()])
            ->get(route('admin.risk-flags.index'))
            ->assertOk()
            ->assertSee('Hủy 3 hóa đơn', false);
    }

    /** The queue names people by implication, so operators must not see it. */
    public function test_an_operator_cannot_read_the_queue(): void
    {
        $cashier = User::factory()->employee()->atBranch($this->branch)->create([
            'can_create_invoices' => true,
        ]);

        $this->actingAs($cashier)
            ->get(route('admin.risk-flags.index'))
            ->assertForbidden();
    }

    /** A reviewer records a conclusion, and it sticks. */
    public function test_a_second_person_can_close_a_flag(): void
    {
        $this->auditEvents(AuditAction::Cancelled, 3);
        app(RiskDetector::class)->sweep(now()->subDays(7));

        $flag = RiskFlag::query()->where('rule', 'invoice_cancellations')->firstOrFail();
        $secondOwner = User::factory()->owner()->atBranch($this->branch)->create();

        $this->actingAs($secondOwner)
            ->patch(route('admin.risk-flags.update', $flag), [
                'review_status' => RiskReviewStatus::Dismissed->value,
                'review_note' => 'Da doi chieu voi khach, dung la doi y that',
            ])
            ->assertSessionHasNoErrors();

        $flag->refresh();

        $this->assertSame(RiskReviewStatus::Dismissed, $flag->review_status);
        $this->assertSame($secondOwner->getKey(), $flag->reviewed_by);
    }

    /**
     * The person the flag is about must not be the one who closes it.
     *
     * Letting them would make the whole queue decorative: whoever is being
     * asked about would simply answer on their own behalf.
     */
    public function test_the_flagged_person_cannot_close_their_own_flag(): void
    {
        $this->auditEvents(AuditAction::Cancelled, 3);
        app(RiskDetector::class)->sweep(now()->subDays(7));

        $flag = RiskFlag::query()->where('rule', 'invoice_cancellations')->firstOrFail();

        $this->actingAs($this->owner)
            ->patch(route('admin.risk-flags.update', $flag), [
                'review_status' => RiskReviewStatus::Dismissed->value,
                'review_note' => 'Toi tu ket luan la khong sao',
            ])
            ->assertForbidden();

        $this->assertTrue($flag->fresh()->isOpen());
    }

    /** A conclusion with no reasoning is no use to the next reader. */
    public function test_closing_a_flag_requires_a_real_note(): void
    {
        $this->auditEvents(AuditAction::Cancelled, 3);
        app(RiskDetector::class)->sweep(now()->subDays(7));

        $flag = RiskFlag::query()->where('rule', 'invoice_cancellations')->firstOrFail();
        $secondOwner = User::factory()->owner()->atBranch($this->branch)->create();

        $this->actingAs($secondOwner)
            ->from(route('admin.risk-flags.index'))
            ->patch(route('admin.risk-flags.update', $flag), [
                'review_status' => RiskReviewStatus::Dismissed->value,
                'review_note' => 'ok',
            ])
            ->assertSessionHasErrors('review_note');

        $this->assertTrue($flag->fresh()->isOpen());
    }

    /**
     * Seed audit events of one kind, attributed to the owner.
     */
    private function auditEvents(AuditAction $action, int $count): void
    {
        $invoice = Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->owner->getKey(),
            'status' => InvoiceStatus::Draft,
        ]);

        for ($index = 0; $index < $count; $index++) {
            AuditEvent::query()->create([
                'auditable_type' => Invoice::class,
                'auditable_id' => $invoice->getKey() + $index,
                'auditable_label' => 'HD-'.$index,
                'action' => $action,
                'branch_id' => $this->branch->getKey(),
                'actor_id' => $this->owner->getKey(),
                'actor_name' => $this->owner->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
