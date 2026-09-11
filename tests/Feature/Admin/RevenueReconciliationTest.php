<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\RiskFlag;
use App\Models\User;
use App\Services\Risk\RiskDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Money that should exist and does not.
 *
 * None of these need anybody to be dishonest to matter: work done and never
 * billed, a receipt written against nothing, a service line crediting nobody.
 * They are the everyday gaps between what the shop did and what the books say
 * it did — and a gap nobody looks at is indistinguishable from one somebody
 * arranged.
 */
class RevenueReconciliationTest extends TestCase
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

    public function test_a_completed_appointment_with_no_invoice_is_flagged(): void
    {
        Appointment::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'employee_id' => $this->owner->getKey(),
            'status' => AppointmentStatus::Completed,
            'customer_name' => 'Khach da lam xong',
        ]);

        app(RiskDetector::class)->sweep(now()->subDays(7));

        $flag = RiskFlag::query()->where('rule', 'completed_appointment_without_invoice')->firstOrFail();

        $this->assertStringContainsString('chưa có hóa đơn', $flag->summary);
    }

    /** An appointment that was billed is not a gap. */
    public function test_a_completed_appointment_with_an_invoice_is_not_flagged(): void
    {
        $appointment = Appointment::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'status' => AppointmentStatus::Completed,
        ]);

        Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->owner->getKey(),
            'appointment_id' => $appointment->getKey(),
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);

        app(RiskDetector::class)->sweep(now()->subDays(7));

        $this->assertSame(0, RiskFlag::query()->where('rule', 'completed_appointment_without_invoice')->count());
    }

    /** A booking still in progress has not been missed yet. */
    public function test_an_unfinished_appointment_is_not_flagged(): void
    {
        Appointment::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'status' => AppointmentStatus::Confirmed,
        ]);

        app(RiskDetector::class)->sweep(now()->subDays(7));

        $this->assertSame(0, RiskFlag::query()->where('rule', 'completed_appointment_without_invoice')->count());
    }

    public function test_a_paid_invoice_with_no_lines_is_flagged(): void
    {
        Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->owner->getKey(),
            'status' => InvoiceStatus::Paid,
            'total' => '150000.00',
            'paid_at' => now(),
        ]);

        app(RiskDetector::class)->sweep(now()->subDays(7));

        $this->assertSame(1, RiskFlag::query()->where('rule', 'paid_invoice_without_value')->count());
    }

    public function test_a_paid_invoice_with_a_zero_total_is_flagged(): void
    {
        $invoice = Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->owner->getKey(),
            'status' => InvoiceStatus::Paid,
            'total' => '0.00',
            'paid_at' => now(),
        ]);

        $invoice->items()->create([
            'name' => 'Dich vu tang',
            'quantity' => 1,
            'unit_price' => 0,
            'line_total' => 0,
            'commission_rate' => 0,
            'commission_amount' => 0,
            'employee_id' => $this->owner->getKey(),
        ]);

        app(RiskDetector::class)->sweep(now()->subDays(7));

        $this->assertSame(1, RiskFlag::query()->where('rule', 'paid_invoice_without_value')->count());
    }

    /** A normal paid invoice raises nothing at all. */
    public function test_a_normal_paid_invoice_is_not_flagged(): void
    {
        $invoice = $this->paidInvoiceWithLine($this->owner->getKey());

        app(RiskDetector::class)->sweep(now()->subDays(7));

        $this->assertSame(0, RiskFlag::query()->where('rule', 'paid_invoice_without_value')->count());
        $this->assertSame(0, RiskFlag::query()->where('rule', 'invoice_line_without_employee')->count());
        $this->assertNotNull($invoice->fresh());
    }

    public function test_a_line_crediting_nobody_is_flagged(): void
    {
        $this->paidInvoiceWithLine(null);

        app(RiskDetector::class)->sweep(now()->subDays(7));

        $flag = RiskFlag::query()->where('rule', 'invoice_line_without_employee')->firstOrFail();

        $this->assertStringContainsString('chưa gán nhân viên', $flag->summary);
    }

    private function paidInvoiceWithLine(?int $employeeId): Invoice
    {
        $invoice = Invoice::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'created_by' => $this->owner->getKey(),
            'status' => InvoiceStatus::Paid,
            'total' => '200000.00',
            'paid_at' => now(),
        ]);

        $invoice->items()->create([
            'name' => 'Son gel',
            'quantity' => 1,
            'unit_price' => 200000,
            'line_total' => 200000,
            'commission_rate' => 0,
            'commission_amount' => 0,
            'employee_id' => $employeeId,
        ]);

        return $invoice;
    }
}
