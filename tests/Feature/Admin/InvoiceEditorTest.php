<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_removing_every_line_clears_the_invoice(): void
    {
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->count(2)->create(['invoice_id' => $invoice->id, 'unit_price' => 100000, 'line_total' => 100000]);

        $this->actingAs($owner)->patch(route('admin.invoices.update', $invoice), [
            'discount' => 0,
            'items_submitted' => 1,
        ])->assertRedirect();

        $invoice->refresh();

        $this->assertSame(0, $invoice->items()->count());
        $this->assertSame('0.00', $invoice->subtotal);
        $this->assertSame('0.00', $invoice->total);
    }

    public function test_a_request_that_never_mentions_items_leaves_them_alone(): void
    {
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_price' => 100000, 'line_total' => 100000]);

        $this->actingAs($owner)->patch(route('admin.invoices.update', $invoice), ['discount' => 10000]);

        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame('90000.00', $invoice->fresh()->total);
    }

    public function test_editing_a_line_updates_it_in_place_instead_of_recreating_it(): void
    {
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();
        $item = InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_price' => 100000, 'line_total' => 100000]);

        $this->actingAs($owner)->patch(route('admin.invoices.update', $invoice), [
            'discount' => 0,
            'items_submitted' => 1,
            'items' => [
                ['id' => $item->id, 'name' => 'Đổi tên', 'quantity' => 3, 'unit_price' => 50000],
            ],
        ]);

        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame($item->id, $invoice->items()->value('id'));
        $this->assertSame('Đổi tên', $invoice->items()->value('name'));
        $this->assertSame('150000.00', $invoice->fresh()->total);
    }

    public function test_a_paid_invoice_renders_its_financial_fields_as_disabled(): void
    {
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_price' => 100000, 'line_total' => 100000]);
        $this->actingAs($owner)->post(route('admin.invoices.pay', $invoice), ['payment_method' => PaymentMethod::Cash->value]);

        $this->actingAs($owner)
            ->get(route('admin.invoices.edit', $invoice))
            ->assertOk()
            ->assertSee('đã bị khóa')
            ->assertSee('name="discount"', false)
            ->assertSee('disabled="disabled"', false)
            ->assertDontSee('Lưu hóa đơn');
    }

    public function test_a_draft_invoice_renders_editable_fields(): void
    {
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();

        $this->actingAs($owner)
            ->get(route('admin.invoices.edit', $invoice))
            ->assertOk()
            ->assertSee('Lưu hóa đơn')
            ->assertDontSee('disabled="disabled"', false);
    }
}
