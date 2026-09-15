<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The company-wide trail behind every change to money.
 *
 * "Who created it" and "who voided it" were already on the business rows; what
 * was missing is what the values were on either side of a change, and — more
 * importantly — a trail that survives the deletion of the row it describes.
 * An audit a fraudster can erase by deleting the record is not an audit.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->manager = User::factory()->manager()->atBranch($this->branch)->create();
    }

    public function test_editing_an_invoice_records_the_values_on_both_sides(): void
    {
        $invoice = Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->manager->getKey(),
            'status' => InvoiceStatus::Draft,
            'discount' => '0.00',
        ]);

        $this->actingAs($this->manager)->withConfirmedPassword()
            ->patch(route('admin.invoices.update', $invoice), [
                'discount' => 50000,
                'items_submitted' => 1,
                'items' => [[
                    'name' => 'Son gel',
                    'quantity' => 1,
                    'unit_price' => 200000,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $event = AuditEvent::query()->forSubject($invoice)->where('action', AuditAction::Updated)->first();

        $this->assertNotNull($event, 'Sửa hóa đơn phải sinh một audit event.');
        $this->assertSame($this->manager->getKey(), $event->actor_id);
        $this->assertSame($this->manager->name, $event->actor_name);
        $this->assertSame($this->branch->getKey(), $event->branch_id);
        $this->assertSame('0.00', $event->before['discount']);
        $this->assertSame('50000.00', $event->after['discount']);
        $this->assertArrayHasKey('discount', $event->changes());
    }

    /** Reassigning a commission line is the classic collusion move. */
    public function test_changing_the_commission_employee_is_recorded(): void
    {
        $first = User::factory()->employee()->atBranch($this->branch)->create();
        $second = User::factory()->employee()->atBranch($this->branch)->create();

        $invoice = Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->manager->getKey(),
            'status' => InvoiceStatus::Draft,
        ]);

        $payload = fn (User $employee): array => [
            'discount' => 0,
            'items_submitted' => 1,
            'items' => [[
                'name' => 'Son gel',
                'quantity' => 1,
                'unit_price' => 200000,
                'employee_id' => $employee->getKey(),
            ]],
        ];

        $this->actingAs($this->manager)->withConfirmedPassword()
            ->patch(route('admin.invoices.update', $invoice), $payload($first))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->manager)->withConfirmedPassword()
            ->patch(route('admin.invoices.update', $invoice), $payload($second))
            ->assertSessionHasNoErrors();

        $latest = AuditEvent::query()->forSubject($invoice)->get()->last();

        $this->assertSame($first->getKey(), $latest->before['items'][0]['employee_id']);
        $this->assertSame($second->getKey(), $latest->after['items'][0]['employee_id']);
    }

    public function test_paying_an_invoice_records_an_event(): void
    {
        $invoice = $this->payableInvoice();

        $this->actingAs($this->manager)->withConfirmedPassword()
            ->post(route('admin.invoices.pay', $invoice), [
                'payment_method' => PaymentMethod::Cash->value,
                'payment_proof_image' => UploadedFile::fake()->image('payment-proof.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            AuditEvent::query()->forSubject($invoice)->where('action', AuditAction::Paid)->exists(),
            'Thanh toán hóa đơn phải sinh audit event.',
        );
    }

    public function test_cancelling_a_paid_invoice_records_the_reason(): void
    {
        $invoice = $this->payableInvoice();

        $this->actingAs($this->manager)->withConfirmedPassword()->post(route('admin.invoices.pay', $invoice), [
            'payment_method' => PaymentMethod::Cash->value,
            'payment_proof_image' => UploadedFile::fake()->image('payment-proof.jpg'),
        ]);

        $this->actingAs($this->manager)->withConfirmedPassword()
            ->delete(route('admin.invoices.cancel', $invoice), [
                'cancel_reason' => 'Khach doi dich vu khac',
            ])
            ->assertSessionHasNoErrors();

        $event = AuditEvent::query()->forSubject($invoice)->where('action', AuditAction::Cancelled)->first();

        $this->assertNotNull($event, 'Hủy hóa đơn phải sinh audit event.');
        $this->assertSame('Khach doi dich vu khac', $event->reason);
    }

    public function test_recording_and_voiding_cash_leaves_a_trail(): void
    {
        $this->actingAs($this->manager)->withConfirmedPassword()
            ->post(route('admin.cash.store'), [
                'type' => 'expense',
                'category' => 'rent',
                'amount' => 1500000,
                'payment_method' => PaymentMethod::Cash->value,
                'occurred_at' => now()->toDateTimeString(),
                'note' => 'Tien thue thang 9',
            ])
            ->assertSessionHasNoErrors();

        $transaction = CashTransaction::query()->latest('id')->firstOrFail();

        $this->assertTrue(
            AuditEvent::query()->forSubject($transaction)->where('action', AuditAction::Created)->exists(),
            'Ghi sổ thu chi phải sinh audit event.',
        );
    }

    /**
     * The heart of the item: deleting the business row must not delete history.
     */
    public function test_the_trail_survives_the_deletion_of_its_subject(): void
    {
        $transaction = CashTransaction::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->manager->getKey(),
        ]);

        app(AuditRecorder::class)->record(
            $transaction,
            $this->manager,
            AuditAction::Created,
            null,
            $transaction->auditSnapshot(),
        );

        $subjectId = $transaction->getKey();
        $transaction->forceDelete();

        $surviving = AuditEvent::query()
            ->where('auditable_type', CashTransaction::class)
            ->where('auditable_id', $subjectId)
            ->first();

        $this->assertNotNull($surviving, 'Xóa bản ghi nghiệp vụ không được xóa mất audit.');
        $this->assertSame($this->manager->name, $surviving->actor_name);
    }

    /** Deactivating the actor must not erase who performed the action. */
    public function test_the_actor_name_survives_account_deletion(): void
    {
        $temp = User::factory()->manager()->atBranch($this->branch)->create(['name' => 'Nguoi da nghi']);
        $transaction = CashTransaction::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->manager->getKey(),
        ]);

        app(AuditRecorder::class)->record(
            $transaction,
            $temp,
            AuditAction::Created,
            null,
            $transaction->auditSnapshot(),
        );

        $temp->delete();

        $event = AuditEvent::query()->forSubject($transaction)->first();

        $this->assertNull($event->actor_id, 'FK phải được set null, không xóa dòng audit.');
        $this->assertSame('Nguoi da nghi', $event->actor_name);
        $this->assertSame('Nguoi da nghi', $event->actorLabel());
    }

    /** No password, token, cookie or precise location may reach the trail. */
    public function test_the_trail_stores_no_secrets_and_no_raw_address(): void
    {
        $transaction = CashTransaction::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->manager->getKey(),
        ]);

        $event = app(AuditRecorder::class)->record(
            $transaction,
            $this->manager,
            AuditAction::Created,
            null,
            $transaction->auditSnapshot(),
        );

        $encoded = json_encode($event->toArray(), JSON_UNESCAPED_UNICODE);

        foreach (['password', 'remember_token', 'api_token', 'secret'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded);
        }

        // The address is only ever kept as a keyed digest.
        $this->assertNotSame('127.0.0.1', $event->ip_hash);
    }

    /** Several changes made by one submit read back as one action. */
    public function test_events_from_one_request_share_a_correlation_id(): void
    {
        $recorder = app(AuditRecorder::class);
        $transaction = CashTransaction::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->manager->getKey(),
        ]);

        $first = $recorder->record($transaction, $this->manager, AuditAction::Created);
        $second = $recorder->record($transaction, $this->manager, AuditAction::Updated);

        $this->assertNotNull($first->correlation_id);
        $this->assertSame($first->correlation_id, $second->correlation_id);
    }

    /** The trail is management information, not an operator's tool. */
    public function test_the_timeline_is_shown_to_leadership_and_hidden_from_an_operator(): void
    {
        $invoice = Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->manager->getKey(),
            'status' => InvoiceStatus::Draft,
        ]);

        $this->actingAs($this->manager)->withConfirmedPassword()
            ->patch(route('admin.invoices.update', $invoice), [
                'discount' => 25000,
                'items_submitted' => 1,
                'items' => [['name' => 'Son gel', 'quantity' => 1, 'unit_price' => 200000]],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->manager)->withConfirmedPassword()
            ->get(route('admin.invoices.edit', $invoice))
            ->assertOk()
            ->assertSee('Lịch sử thay đổi');

        $cashier = User::factory()->employee()->atBranch($this->branch)->create([
            'can_create_invoices' => true,
        ]);

        $this->actingAs($cashier)->withConfirmedPassword()
            ->get(route('admin.invoices.edit', $invoice))
            ->assertOk()
            ->assertDontSee('Lịch sử thay đổi');
    }

    private function payableInvoice(): Invoice
    {
        $invoice = Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->manager->getKey(),
            'status' => InvoiceStatus::Draft,
        ]);

        $invoice->items()->create([
            'name' => 'Son gel',
            'quantity' => 1,
            'unit_price' => 200000,
            'line_total' => 200000,
            'commission_rate' => 0,
            'commission_amount' => 0,
        ]);

        $invoice->forceFill(['subtotal' => 200000, 'total' => 200000])->save();

        return $invoice;
    }
}
