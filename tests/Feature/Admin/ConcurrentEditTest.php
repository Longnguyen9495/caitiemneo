<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two people editing the same record.
 *
 * The last save silently wins, so whoever pressed the button second erases the
 * other's work without either of them knowing. On an invoice that means a
 * discount or a line quietly reverts after somebody has already agreed it with
 * the customer — and the audit trail faithfully records both edits, which makes
 * the disappearance look deliberate rather than accidental.
 *
 * The fix is to say so: a submit built on a version that has since moved is
 * refused and the operator is told to reload.
 */
class ConcurrentEditTest extends TestCase
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

    /** A submit carrying a stale version must be refused, not applied. */
    public function test_a_stale_invoice_edit_is_refused(): void
    {
        $invoice = $this->draftInvoice();

        $staleVersion = $invoice->updated_at->timestamp;

        // Người thứ nhất lưu sau đó một lúc, nên updated_at thực sự đổi.
        // Không có bước dịch thời gian này thì cả hai lần lưu rơi vào cùng một
        // giây và test sẽ xanh mà chẳng chứng minh được gì.
        $this->travel(5)->seconds();

        $this->actingAs($this->manager)
            ->patch(route('admin.invoices.update', $invoice), $this->payload($invoice, 'Nguoi thu nhat', 30000))
            ->assertSessionHasNoErrors();

        $this->assertNotSame($staleVersion, $invoice->fresh()->updated_at->timestamp, 'Phiên bản phải đổi sau khi lưu.');

        $this->actingAs($this->manager)
            ->from(route('admin.invoices.edit', $invoice))
            ->patch(route('admin.invoices.update', $invoice), array_merge(
                $this->payload($invoice, 'Nguoi thu hai', 90000),
                ['expected_version' => $staleVersion],
            ))
            ->assertSessionHasErrors('expected_version');

        // Công của người thứ nhất phải còn nguyên.
        $this->assertSame('Nguoi thu nhat', $invoice->fresh()->customer_name);
    }

    /** A submit carrying the current version goes through as normal. */
    public function test_a_current_invoice_edit_is_applied(): void
    {
        $invoice = $this->draftInvoice();

        $this->actingAs($this->manager)
            ->patch(route('admin.invoices.update', $invoice), array_merge(
                $this->payload($invoice, 'Khach moi', 20000),
                ['expected_version' => $invoice->updated_at->timestamp],
            ))
            ->assertSessionHasNoErrors();

        $this->assertSame('Khach moi', $invoice->fresh()->customer_name);
    }

    /**
     * A form that does not send a version at all keeps working.
     *
     * Not every screen has been through this yet, and refusing those submits
     * would break working flows to guard against a rarer problem.
     */
    public function test_a_submit_without_a_version_is_still_accepted(): void
    {
        $invoice = $this->draftInvoice();

        $this->actingAs($this->manager)
            ->patch(route('admin.invoices.update', $invoice), $this->payload($invoice, 'Khong gui version', 10000))
            ->assertSessionHasNoErrors();

        $this->assertSame('Khong gui version', $invoice->fresh()->customer_name);
    }

    /** The edit screen has to hand the browser the version it is editing. */
    public function test_the_edit_form_carries_the_current_version(): void
    {
        $invoice = $this->draftInvoice();

        $this->actingAs($this->manager)
            ->get(route('admin.invoices.edit', $invoice))
            ->assertOk()
            ->assertSee('name="expected_version"', false)
            ->assertSee('value="'.$invoice->updated_at->timestamp.'"', false);
    }

    /** Payroll overlap is settled under a row lock, not by a bare query. */
    public function test_two_payrolls_cannot_cover_the_same_period(): void
    {
        $employee = User::factory()->employee()->atBranch($this->branch)->create();
        $payrollManager = User::factory()->payrollManager()->atBranch($this->branch)->create();

        Payroll::factory()->create([
            'employee_id' => $employee->getKey(),
            'paying_branch_id' => $this->branch->getKey(),
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
        ]);

        $this->actingAs($payrollManager)
            ->from(route('admin.payrolls.create'))
            ->post(route('admin.payrolls.store'), [
                'employee_id' => $employee->getKey(),
                // Chồng lấn một phần: unique trên đúng cặp ngày không chặn được.
                'period_start' => '2026-09-15',
                'period_end' => '2026-10-15',
            ])
            ->assertSessionHasErrors('period_start');

        $this->assertSame(1, Payroll::query()->where('employee_id', $employee->getKey())->count());
    }

    private function draftInvoice(): Invoice
    {
        return Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->manager->getKey(),
            'status' => InvoiceStatus::Draft,
            'customer_name' => 'Khach ban dau',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Invoice $invoice, string $customerName, int $discount): array
    {
        return [
            'customer_name' => $customerName,
            'discount' => $discount,
            'items_submitted' => 1,
            'items' => [[
                'name' => 'Son gel',
                'quantity' => 1,
                'unit_price' => 200000,
            ]],
        ];
    }
}
