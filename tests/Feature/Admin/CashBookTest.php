<?php

namespace Tests\Feature\Admin;

use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\PaymentMethod;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manual_entry_is_always_stored_as_a_positive_amount(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(route('admin.cash.store'), [
            'type' => CashTransactionType::Expense->value,
            'category' => CashTransactionCategory::Rent->value,
            'amount' => 4500000,
            'payment_method' => PaymentMethod::Transfer->value,
            'occurred_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'note' => 'Tiền thuê tháng 8',
        ])->assertRedirect(route('admin.cash.index'));

        $transaction = CashTransaction::query()->firstOrFail();

        $this->assertSame('4500000.00', $transaction->amount);
        $this->assertSame(CashTransactionType::Expense, $transaction->type);
        $this->assertSame($owner->id, $transaction->created_by);
    }

    public function test_system_categories_cannot_be_chosen_by_hand(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->from(route('admin.cash.create'))
            ->post(route('admin.cash.store'), [
                'type' => CashTransactionType::Income->value,
                'category' => CashTransactionCategory::ServiceRevenue->value,
                'amount' => 100000,
                'occurred_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors('category');

        $this->assertSame(0, CashTransaction::query()->count());
    }

    public function test_a_non_positive_amount_is_rejected(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->from(route('admin.cash.create'))
            ->post(route('admin.cash.store'), [
                'type' => CashTransactionType::Expense->value,
                'category' => CashTransactionCategory::Rent->value,
                'amount' => 0,
                'occurred_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_an_invoice_generated_entry_cannot_be_edited_or_voided(): void
    {
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_price' => 100000, 'line_total' => 100000]);
        $this->actingAs($owner)->post(route('admin.invoices.pay', $invoice), ['payment_method' => PaymentMethod::Cash->value]);

        $transaction = CashTransaction::query()->firstOrFail();

        $this->actingAs($owner)->get(route('admin.cash.edit', $transaction))->assertForbidden();
        $this->actingAs($owner)->delete(route('admin.cash.destroy', $transaction), ['void_reason' => 'Thử'])->assertForbidden();
    }

    public function test_voiding_a_manual_entry_keeps_the_row_and_drops_it_from_the_balance(): void
    {
        // Người hủy phải là người thứ hai: xem CashMakerCheckerTest.
        $author = User::factory()->owner()->create();
        $owner = User::factory()->owner()->create();
        $transaction = CashTransaction::factory()->create(['amount' => 200000, 'created_by' => $author->id]);

        $this->actingAs($owner)
            ->delete(route('admin.cash.destroy', $transaction), ['void_reason' => 'Ghi nhầm số tiền khi nhập'])
            ->assertRedirect(route('admin.cash.index'));

        $transaction->refresh();
        $this->assertNotNull($transaction->voided_at);
        $this->assertSame($owner->id, $transaction->voided_by);
        $this->assertSame('Ghi nhầm số tiền khi nhập', $transaction->void_reason);
        $this->assertSame(1, CashTransaction::query()->count());
        $this->assertSame(0, CashTransaction::query()->active()->count());
    }

    public function test_the_filtered_totals_reflect_the_current_filter(): void
    {
        $owner = User::factory()->owner()->create();

        CashTransaction::factory()->income()->create(['amount' => 500000, 'occurred_at' => '2026-08-10 10:00:00']);
        CashTransaction::factory()->create(['amount' => 200000, 'occurred_at' => '2026-08-11 10:00:00']);
        CashTransaction::factory()->income()->create(['amount' => 900000, 'occurred_at' => '2026-09-11 10:00:00']);

        $this->actingAs($owner)
            ->get(route('admin.cash.index', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk()
            ->assertSee('500.000')
            ->assertSee('300.000')
            ->assertDontSee('900.000');
    }

    public function test_a_voided_entry_is_hidden_unless_requested(): void
    {
        $owner = User::factory()->owner()->create();
        $transaction = CashTransaction::factory()->create(['amount' => 123000, 'note' => 'Khoản đã hủy']);
        $this->actingAs($owner)->delete(route('admin.cash.destroy', $transaction), ['void_reason' => 'Nhập sai hạng mục']);

        $this->actingAs($owner)->get(route('admin.cash.index'))->assertDontSee('Khoản đã hủy');
        $this->actingAs($owner)->get(route('admin.cash.index', ['include_voided' => 1]))->assertSee('Khoản đã hủy');
    }
}
