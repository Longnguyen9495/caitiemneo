<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function appointmentWithServices(User $employee): Appointment
    {
        $appointment = Appointment::factory()
            ->status(AppointmentStatus::Completed)
            ->create(['employee_id' => $employee->id]);

        $service = Service::factory()->create(['price' => 300000]);

        AppointmentService::query()->create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'price' => $service->price,
        ]);

        return $appointment;
    }

    public function test_converting_an_appointment_creates_exactly_one_invoice(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->employee()->create(['commission_rate' => 10]);
        $appointment = $this->appointmentWithServices($employee);

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.appointments.convert-to-invoice', $appointment))->assertRedirect();
        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.appointments.convert-to-invoice', $appointment))->assertRedirect();

        $this->assertSame(1, Invoice::query()->count());

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('300000.00', $invoice->total);
        $this->assertSame('30000.00', $invoice->items()->value('commission_amount'));
    }

    public function test_the_server_recalculates_totals_and_ignores_client_supplied_amounts(): void
    {
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();

        $this->actingAs($owner)->withConfirmedPassword()->patch(route('admin.invoices.update', $invoice), [
            'discount' => 50000,
            'total' => 1,
            'subtotal' => 1,
            'items' => [
                [
                    'name' => 'Sơn gel',
                    'quantity' => 2,
                    'unit_price' => 200000,
                    'commission_rate' => 10,
                    'commission_rate_reason' => 'Chốt riêng với khách quen',
                ],
            ],
        ])->assertRedirect();

        $invoice->refresh();

        $this->assertSame('400000.00', $invoice->subtotal);
        $this->assertSame('50000.00', $invoice->discount);
        $this->assertSame('350000.00', $invoice->total);
        $this->assertSame('400000.00', $invoice->items()->value('line_total'));
        $this->assertSame('40000.00', $invoice->items()->value('commission_amount'));
    }

    public function test_a_discount_larger_than_the_subtotal_floors_the_total_at_zero(): void
    {
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();

        $this->actingAs($owner)->withConfirmedPassword()->patch(route('admin.invoices.update', $invoice), [
            'discount' => 900000,
            'items' => [['name' => 'Dịch vụ', 'quantity' => 1, 'unit_price' => 100000]],
        ]);

        $this->assertSame('0.00', $invoice->fresh()->total);
    }

    public function test_paying_an_invoice_requires_and_stores_one_payment_proof_image(): void
    {
        Storage::fake('local');
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_price' => 250000, 'line_total' => 250000]);

        $this->actingAs($owner)->withConfirmedPassword()
            ->from(route('admin.invoices.edit', $invoice))
            ->post(route('admin.invoices.pay', $invoice), ['payment_method' => PaymentMethod::Cash->value])
            ->assertRedirect(route('admin.invoices.edit', $invoice))
            ->assertSessionHasErrors('payment_proof_image');

        $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status);
        $this->assertSame(0, CashTransaction::query()->count());

        $this->actingAs($owner)->withConfirmedPassword()
            ->post(route('admin.invoices.pay', $invoice), [
                'payment_method' => PaymentMethod::Cash->value,
                'payment_proof_image' => UploadedFile::fake()->image('payment-proof.jpg'),
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertTrue($invoice->qualified_for_bill_kpi);
        $this->assertSame($owner->id, $invoice->bill_kpi_verified_by);
        $this->assertNotNull($invoice->bill_kpi_verified_at);
        $this->assertSame('250000.00', $invoice->total);
        $this->assertNotNull($invoice->bill_image_path);
        $this->assertTrue(Storage::disk('local')->exists($invoice->bill_image_path));

        $transactions = CashTransaction::query()->where('invoice_id', $invoice->id)->get();
        $this->assertCount(1, $transactions);
        $this->assertSame(CashTransactionType::Income, $transactions->first()->type);
        $this->assertSame(CashTransactionCategory::ServiceRevenue, $transactions->first()->category);
        $this->assertSame('250000.00', $transactions->first()->amount);
    }

    public function test_transfer_payment_uses_the_same_single_payment_proof_image(): void
    {
        Storage::fake('local');
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();

        $this->actingAs($owner)->withConfirmedPassword()
            ->from(route('admin.invoices.edit', $invoice))
            ->post(route('admin.invoices.pay', $invoice), [
                'payment_method' => PaymentMethod::Transfer->value,
            ])
            ->assertRedirect(route('admin.invoices.edit', $invoice))
            ->assertSessionHasErrors('payment_proof_image');

        $this->actingAs($owner)->withConfirmedPassword()
            ->post(route('admin.invoices.pay', $invoice), [
                'payment_method' => PaymentMethod::Transfer->value,
                'payment_proof_image' => UploadedFile::fake()->image('transfer-proof.jpg'),
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertTrue($invoice->qualified_for_bill_kpi);
        $this->assertSame($owner->id, $invoice->bill_kpi_verified_by);
        $this->assertNotNull($invoice->bill_image_path);
        $this->assertTrue(Storage::disk('local')->exists($invoice->bill_image_path));
    }

    public function test_a_double_submitted_payment_does_not_duplicate_the_transaction(): void
    {
        Storage::fake('local');
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_price' => 100000, 'line_total' => 100000]);

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.invoices.pay', $invoice), [
            'payment_method' => PaymentMethod::Cash->value,
            'payment_proof_image' => UploadedFile::fake()->image('payment-proof.jpg'),
        ]);
        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.invoices.pay', $invoice), [
            'payment_method' => PaymentMethod::Transfer->value,
            'payment_proof_image' => UploadedFile::fake()->image('another-payment-proof.jpg'),
        ]);

        $this->assertSame(1, CashTransaction::query()->where('invoice_id', $invoice->id)->count());
        $this->assertSame(PaymentMethod::Cash, $invoice->fresh()->payment_method);
    }

    public function test_a_paid_invoice_can_no_longer_be_edited(): void
    {
        Storage::fake('local');
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_price' => 100000, 'line_total' => 100000]);

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.invoices.pay', $invoice), [
            'payment_method' => PaymentMethod::Cash->value,
            'payment_proof_image' => UploadedFile::fake()->image('payment-proof.jpg'),
        ]);

        $this->actingAs($owner)->withConfirmedPassword()->patch(route('admin.invoices.update', $invoice), [
            'discount' => 90000,
            'items' => [['name' => 'Thay đổi', 'quantity' => 1, 'unit_price' => 1]],
        ])->assertForbidden();

        $this->assertSame('100000.00', $invoice->fresh()->total);
    }

    public function test_cancelling_a_paid_invoice_keeps_the_income_and_adds_a_reversal(): void
    {
        Storage::fake('local');
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_price' => 180000, 'line_total' => 180000]);

        $this->actingAs($owner)->withConfirmedPassword()->post(route('admin.invoices.pay', $invoice), [
            'payment_method' => PaymentMethod::Cash->value,
            'payment_proof_image' => UploadedFile::fake()->image('payment-proof.jpg'),
        ]);
        $this->actingAs($owner)->withConfirmedPassword()->delete(route('admin.invoices.cancel', $invoice), ['cancel_reason' => 'Khách đổi ý'])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->status);
        $this->assertSame('Khách đổi ý', $invoice->cancel_reason);

        $transactions = CashTransaction::query()->where('invoice_id', $invoice->id)->orderBy('id')->get();
        $this->assertCount(2, $transactions);
        $this->assertSame(CashTransactionType::Income, $transactions[0]->type);
        $this->assertSame(CashTransactionType::Expense, $transactions[1]->type);
        $this->assertSame(CashTransactionCategory::Refund, $transactions[1]->category);
        $this->assertSame('180000.00', $transactions[1]->amount);
        $this->assertSame($transactions[0]->id, $transactions[1]->reverses_transaction_id);
    }

    public function test_cancelling_a_draft_invoice_touches_no_money(): void
    {
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();

        $this->actingAs($owner)->withConfirmedPassword()->delete(route('admin.invoices.cancel', $invoice), ['cancel_reason' => 'Nhập nhầm']);

        $this->assertSame(InvoiceStatus::Cancelled, $invoice->fresh()->status);
        $this->assertSame(0, CashTransaction::query()->count());
    }

    public function test_an_employee_without_the_invoice_permission_cannot_pay(): void
    {
        $employee = User::factory()->employee()->create(['can_create_invoices' => false]);
        $invoice = Invoice::factory()->create();

        $this->actingAs($employee)->withConfirmedPassword()
            ->post(route('admin.invoices.pay', $invoice), ['payment_method' => PaymentMethod::Cash->value])
            ->assertForbidden();
    }

    public function test_only_leadership_may_cancel_an_invoice(): void
    {
        $employee = User::factory()->employee()->create(['can_create_invoices' => true]);
        $invoice = Invoice::factory()->create();

        $this->actingAs($employee)->withConfirmedPassword()
            ->delete(route('admin.invoices.cancel', $invoice), ['cancel_reason' => 'Thử'])
            ->assertForbidden();
    }

    public function test_the_invoice_list_filters_and_keeps_the_query_string(): void
    {
        $owner = User::factory()->owner()->create();
        Invoice::factory()->create(['number' => 'NEO-FIND-ME', 'customer_name' => 'Chị Lan']);
        Invoice::factory()->count(3)->create(['customer_name' => 'Khách khác']);

        $this->actingAs($owner)->withConfirmedPassword()
            ->get(route('admin.invoices.index', ['search' => 'FIND-ME']))
            ->assertOk()
            ->assertSee('NEO-FIND-ME')
            ->assertDontSee('Khách khác');
    }

    public function test_the_invoice_update_rejects_an_invalid_payload(): void
    {
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();

        $this->actingAs($owner)->withConfirmedPassword()
            ->from(route('admin.invoices.edit', $invoice))
            ->patch(route('admin.invoices.update', $invoice), [
                'discount' => -5,
                'items' => [['name' => '', 'quantity' => 0, 'unit_price' => -1]],
            ])
            ->assertRedirect(route('admin.invoices.edit', $invoice))
            ->assertSessionHasErrors(['discount', 'items.0.name', 'items.0.quantity', 'items.0.unit_price']);
    }
}
