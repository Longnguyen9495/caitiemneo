<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\InvoiceStatus;
use App\Models\AuditEvent;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sensitive abilities inside an invoice.
 *
 * `can_create_invoices` used to carry everything: an operator could price a
 * service below the branch range, discount without limit, credit the commission
 * to whoever they liked and then take the payment — the whole undercharge-and-
 * kickback loop, with nobody else involved.
 *
 * The price range stays a judgement call rather than a hard block (a genuinely
 * difficult job may exceed it), so the rule is: an operator bills inside the
 * range, and going outside it needs leadership plus a reason on the record.
 */
class InvoiceSensitivePermissionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $cashier;

    private User $manager;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();

        $this->cashier = User::factory()->employee()->atBranch($this->branch)->create([
            'can_create_invoices' => true,
        ]);
        $this->manager = User::factory()->manager()->atBranch($this->branch)->create();

        $this->service = Service::factory()->create(['name' => 'Ve mong nghe thuat']);

        BranchService::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'service_id' => $this->service->getKey(),
            'price' => '300000.00',
            'price_min' => '200000.00',
            'price_max' => '400000.00',
            'is_active' => true,
        ]);
    }

    public function test_an_operator_may_bill_inside_the_branch_price_range(): void
    {
        $this->actingAs($this->cashier)
            ->patch(route('admin.invoices.update', $this->draft()), $this->payload(['unit_price' => 350000]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invoice_items', ['unit_price' => '350000.00']);
    }

    public function test_an_operator_may_not_price_below_the_branch_range(): void
    {
        $invoice = $this->draft();

        $this->actingAs($this->cashier)
            ->patch(route('admin.invoices.update', $invoice), $this->payload(['unit_price' => 50000]))
            ->assertSessionHasErrors('items.0.unit_price');

        $this->assertSame(0, $invoice->items()->count());
    }

    public function test_an_operator_may_not_price_above_the_branch_range_either(): void
    {
        $this->actingAs($this->cashier)
            ->patch(route('admin.invoices.update', $this->draft()), $this->payload(['unit_price' => 900000]))
            ->assertSessionHasErrors('items.0.unit_price');
    }

    /** Leadership may go outside the range, but only with a reason on file. */
    public function test_a_manager_needs_a_reason_to_price_outside_the_range(): void
    {
        $this->actingAs($this->manager)
            ->patch(route('admin.invoices.update', $this->draft()), $this->payload(['unit_price' => 50000]))
            ->assertSessionHasErrors('items.0.price_override_reason');
    }

    public function test_a_manager_with_a_reason_may_price_outside_the_range(): void
    {
        $this->actingAs($this->manager)
            ->patch(route('admin.invoices.update', $this->draft()), $this->payload([
                'unit_price' => 50000,
                'price_override_reason' => 'Khach quen, lam lai phan bi loi',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invoice_items', ['unit_price' => '50000.00']);
    }

    /** The override has to be reconstructible afterwards. */
    public function test_a_price_override_is_written_to_the_audit_trail(): void
    {
        $invoice = $this->draft();

        $this->actingAs($this->manager)
            ->patch(route('admin.invoices.update', $invoice), $this->payload([
                'unit_price' => 50000,
                'price_override_reason' => 'Khach quen, lam lai phan bi loi',
            ]))
            ->assertSessionHasNoErrors();

        $event = AuditEvent::query()->forSubject($invoice)->where('action', AuditAction::Updated)->firstOrFail();

        $this->assertStringContainsString('Khach quen', (string) $event->reason);
    }

    /** A service with no range is unconstrained, as before. */
    public function test_a_service_without_a_range_is_not_constrained(): void
    {
        $free = Service::factory()->create();
        BranchService::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'service_id' => $free->getKey(),
            'price' => '100000.00',
            'price_min' => null,
            'price_max' => null,
            'is_active' => true,
        ]);

        $this->actingAs($this->cashier)
            ->patch(route('admin.invoices.update', $this->draft()), $this->payload([
                'service_id' => $free->getKey(),
                'unit_price' => 9999000,
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_an_operator_may_not_discount_beyond_the_threshold(): void
    {
        $invoice = $this->draft();

        $this->actingAs($this->cashier)
            ->patch(route('admin.invoices.update', $invoice), $this->payload([
                'unit_price' => 300000,
            ], ['discount' => 290000]))
            ->assertSessionHasErrors('discount');
    }

    public function test_a_small_discount_stays_with_the_operator(): void
    {
        $this->actingAs($this->cashier)
            ->patch(route('admin.invoices.update', $this->draft()), $this->payload([
                'unit_price' => 300000,
            ], ['discount' => 20000]))
            ->assertSessionHasNoErrors();
    }

    public function test_a_manager_may_apply_a_large_discount(): void
    {
        $this->actingAs($this->manager)
            ->patch(route('admin.invoices.update', $this->draft()), $this->payload([
                'unit_price' => 300000,
            ], ['discount' => 290000]))
            ->assertSessionHasNoErrors();
    }

    private function draft(): Invoice
    {
        return Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->manager->getKey(),
            'status' => InvoiceStatus::Draft,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $top
     * @return array<string, mixed>
     */
    private function payload(array $row = [], array $top = []): array
    {
        return array_merge([
            'discount' => 0,
            'items_submitted' => 1,
            'items' => [array_merge([
                'service_id' => $this->service->getKey(),
                'name' => 'Ve mong nghe thuat',
                'quantity' => 1,
                'unit_price' => 300000,
            ], $row)],
        ], $top);
    }
}
