<?php

namespace Tests\Feature\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Foreign keys submitted by a form must be proven to belong to the branch, to
 * be active, and — where the relation is dated — to be in force on the business
 * date of the record. A bare `exists` check lets a tampered payload reach
 * across branches, which is the cheapest internal fraud there is.
 */
class ScopedForeignIdTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $otherBranch;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->otherBranch = Branch::factory()->create();
        $this->manager = User::factory()->manager()->atBranch($this->branch)->create();
    }

    /** Commission must not be routed to somebody who does not work here. */
    public function test_invoice_line_rejects_an_employee_from_another_branch(): void
    {
        $outsider = User::factory()->employee()->atBranch($this->otherBranch)->create();
        $invoice = $this->draftInvoice();

        $this->actingAs($this->manager)
            ->patch(route('admin.invoices.update', $invoice), $this->invoicePayload([
                'employee_id' => $outsider->getKey(),
            ]))
            ->assertSessionHasErrors('items.0.employee_id');

        $this->assertDatabaseMissing('invoice_items', ['employee_id' => $outsider->getKey()]);
    }

    /** A posting that has already ended cannot earn commission today. */
    public function test_invoice_line_rejects_an_employee_whose_posting_has_expired(): void
    {
        $former = User::factory()->employee()
            ->atBranch($this->branch, true, '2000-01-01', '2000-12-31')
            ->create();

        $this->actingAs($this->manager)
            ->patch(route('admin.invoices.update', $this->draftInvoice()), $this->invoicePayload([
                'employee_id' => $former->getKey(),
            ]))
            ->assertSessionHasErrors('items.0.employee_id');
    }

    public function test_invoice_line_rejects_a_deactivated_employee(): void
    {
        $suspended = User::factory()->employee()->inactive()->atBranch($this->branch)->create();

        $this->actingAs($this->manager)
            ->patch(route('admin.invoices.update', $this->draftInvoice()), $this->invoicePayload([
                'employee_id' => $suspended->getKey(),
            ]))
            ->assertSessionHasErrors('items.0.employee_id');
    }

    public function test_invoice_line_accepts_an_employee_posted_to_the_branch(): void
    {
        $worker = User::factory()->employee()->atBranch($this->branch)->create();

        $this->actingAs($this->manager)
            ->patch(route('admin.invoices.update', $this->draftInvoice()), $this->invoicePayload([
                'employee_id' => $worker->getKey(),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invoice_items', ['employee_id' => $worker->getKey()]);
    }

    /** A service outside the branch catalogue must be refused, not silently dropped. */
    public function test_invoice_line_rejects_a_service_outside_the_branch_catalogue(): void
    {
        $foreignService = Service::factory()->create();

        $this->actingAs($this->manager)
            ->patch(route('admin.invoices.update', $this->draftInvoice()), $this->invoicePayload([
                'service_id' => $foreignService->getKey(),
            ]))
            ->assertSessionHasErrors('items.0.service_id');
    }

    /** An invoice line id belonging to a different invoice must not be adoptable. */
    public function test_invoice_line_rejects_an_item_id_from_another_invoice(): void
    {
        $mine = $this->draftInvoice();
        $theirs = $this->draftInvoice();

        $foreignItem = $theirs->items()->create([
            'name' => 'Dong cua hoa don khac',
            'quantity' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
            'commission_rate' => 0,
            'commission_amount' => 0,
        ]);

        $this->actingAs($this->manager)
            ->patch(route('admin.invoices.update', $mine), $this->invoicePayload([
                'id' => $foreignItem->getKey(),
                'unit_price' => 999000,
            ]))
            ->assertSessionHasErrors('items.0.id');

        $this->assertDatabaseHas('invoice_items', [
            'id' => $foreignItem->getKey(),
            'invoice_id' => $theirs->getKey(),
        ]);
    }

    /** Attendance may only be written for staff posted to the branch that day. */
    public function test_manual_attendance_rejects_an_employee_from_another_branch(): void
    {
        $outsider = User::factory()->employee()->atBranch($this->otherBranch)->create();

        $this->actingAs($this->manager)
            ->post(route('admin.attendance.store'), $this->attendancePayload($outsider))
            ->assertSessionHasErrors('employee_id');

        $this->assertDatabaseMissing('attendance_records', ['employee_id' => $outsider->getKey()]);
    }

    /** The posting has to cover the work date, not merely exist. */
    public function test_manual_attendance_rejects_a_work_date_outside_the_posting(): void
    {
        $former = User::factory()->employee()
            ->atBranch($this->branch, true, '2000-01-01', '2000-12-31')
            ->create();

        $this->actingAs($this->manager)
            ->post(route('admin.attendance.store'), $this->attendancePayload($former))
            ->assertSessionHasErrors('employee_id');
    }

    public function test_manual_attendance_accepts_a_posted_employee(): void
    {
        $worker = User::factory()->employee()->atBranch($this->branch)->create();

        $this->actingAs($this->manager)
            ->post(route('admin.attendance.store'), $this->attendancePayload($worker))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendance_records', ['employee_id' => $worker->getKey()]);
    }

    public function test_roster_rejects_a_work_shift_template_of_another_branch(): void
    {
        $foreignShift = WorkShift::factory()->create(['branch_id' => $this->otherBranch->getKey()]);
        $worker = User::factory()->employee()->atBranch($this->branch)->create();

        $this->actingAs($this->manager)
            ->post(route('admin.shift-schedule.store'), [
                'employee_id' => $worker->getKey(),
                'work_shift_id' => $foreignShift->getKey(),
                'work_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('work_shift_id');
    }

    public function test_roster_rejects_an_employee_of_another_branch(): void
    {
        $shift = WorkShift::factory()->create(['branch_id' => $this->branch->getKey()]);
        $outsider = User::factory()->employee()->atBranch($this->otherBranch)->create();

        $this->actingAs($this->manager)
            ->post(route('admin.shift-schedule.store'), [
                'employee_id' => $outsider->getKey(),
                'work_shift_id' => $shift->getKey(),
                'work_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('employee_id');
    }

    public function test_inventory_movement_rejects_an_inactive_product(): void
    {
        $retired = Product::factory()->create(['is_active' => false]);

        $this->actingAs($this->manager)
            ->post(route('admin.inventory.store'), [
                'product_id' => $retired->getKey(),
                'type' => InventoryMovementType::In->value,
                'quantity' => 5,
                'occurred_at' => now()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('product_id');
    }

    public function test_inventory_movement_rejects_an_inactive_supplier(): void
    {
        $product = Product::factory()->create();
        $retired = Supplier::factory()->create(['is_active' => false]);

        $this->actingAs($this->manager)
            ->post(route('admin.inventory.store'), [
                'product_id' => $product->getKey(),
                'supplier_id' => $retired->getKey(),
                'type' => InventoryMovementType::In->value,
                'quantity' => 5,
                'occurred_at' => now()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('supplier_id');
    }

    public function test_stock_transfer_rejects_an_inactive_product(): void
    {
        $retired = Product::factory()->create(['is_active' => false]);

        $this->actingAs($this->manager)
            ->post(route('admin.stock-transfers.store'), [
                'source_branch_id' => $this->branch->getKey(),
                'destination_branch_id' => $this->otherBranch->getKey(),
                'items' => [['product_id' => $retired->getKey(), 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('items.0.product_id');
    }

    /** Payroll must not be created for staff the manager does not supervise. */
    public function test_payroll_rejects_an_employee_outside_the_actor_scope(): void
    {
        $payrollManager = User::factory()->payrollManager()->atBranch($this->branch)->create();
        $outsider = User::factory()->employee()->atBranch($this->otherBranch)->create();

        $this->actingAs($payrollManager)
            ->post(route('admin.payrolls.store'), [
                'employee_id' => $outsider->getKey(),
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
            ])
            ->assertSessionHasErrors('employee_id');

        $this->assertDatabaseMissing('payrolls', ['employee_id' => $outsider->getKey()]);
    }

    public function test_payroll_rejects_a_paying_branch_outside_the_actor_scope(): void
    {
        $payrollManager = User::factory()->payrollManager()->atBranch($this->branch)->create();
        $worker = User::factory()->employee()->atBranch($this->branch)->create();

        $this->actingAs($payrollManager)
            ->post(route('admin.payrolls.store'), [
                'employee_id' => $worker->getKey(),
                'paying_branch_id' => $this->otherBranch->getKey(),
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
            ])
            ->assertSessionHasErrors('paying_branch_id');
    }

    private function draftInvoice(): Invoice
    {
        return Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'status' => InvoiceStatus::Draft,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function invoicePayload(array $overrides): array
    {
        return [
            'discount' => 0,
            'items_submitted' => 1,
            'items' => [array_merge([
                'name' => 'Son gel',
                'quantity' => 1,
                'unit_price' => 200000,
            ], $overrides)],
        ];
    }

    /** @return array<string, mixed> */
    private function attendancePayload(User $employee): array
    {
        return [
            'employee_id' => $employee->getKey(),
            'work_date' => now()->toDateString(),
            'shift_name' => 'Ca sang',
            'shift_value' => 1,
            'status' => AttendanceStatus::Present->value,
            'reason' => 'Nhap bu cho ca sang',
        ];
    }
}
