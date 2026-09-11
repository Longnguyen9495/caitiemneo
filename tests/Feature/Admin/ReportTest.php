<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InvoiceStatus;
use App\Enums\PayrollStatus;
use App\Models\Appointment;
use App\Models\CashTransaction;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\User;
use App\Services\ReportService;
use App\Support\Money;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function period(string $from = '2026-08-01', string $to = '2026-08-31'): ReportPeriod
    {
        return ReportPeriod::fromRequest(Request::create('/', 'GET', ['from' => $from, 'to' => $to]));
    }

    public function test_revenue_is_measured_on_the_payment_date(): void
    {
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'paid_at' => '2026-08-15 10:00:00', 'total' => 500000]);
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'paid_at' => '2026-09-01 10:00:00', 'total' => 700000]);
        Invoice::factory()->create(['status' => InvoiceStatus::Draft, 'total' => 900000]);

        $summary = app(ReportService::class)->summary($this->period());

        $this->assertSame('500000.00', $summary['revenue']);
        $this->assertSame(1, $summary['invoice_count']);
    }

    public function test_the_period_boundaries_are_inclusive(): void
    {
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'paid_at' => '2026-08-01 00:00:01', 'total' => 100000]);
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'paid_at' => '2026-08-31 23:59:00', 'total' => 200000]);
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'paid_at' => '2026-07-31 23:59:00', 'total' => 400000]);

        $this->assertSame('300000.00', app(ReportService::class)->summary($this->period())['revenue']);
    }

    public function test_cash_flow_uses_the_transaction_date_not_the_invoice_date(): void
    {
        CashTransaction::factory()->income()->create(['amount' => 800000, 'occurred_at' => '2026-08-05 10:00:00']);
        CashTransaction::factory()->create(['amount' => 300000, 'occurred_at' => '2026-08-06 10:00:00']);
        CashTransaction::factory()->income()->create(['amount' => 100000, 'occurred_at' => '2026-09-06 10:00:00']);

        $summary = app(ReportService::class)->summary($this->period());

        $this->assertSame('800000.00', $summary['cash_income']);
        $this->assertSame('300000.00', $summary['cash_expense']);
        $this->assertSame('500000.00', $summary['cash_net']);
    }

    public function test_a_voided_transaction_leaves_the_cash_flow(): void
    {
        CashTransaction::factory()->income()->create(['amount' => 500000, 'occurred_at' => '2026-08-05 10:00:00']);
        CashTransaction::factory()->income()->create([
            'amount' => 400000,
            'occurred_at' => '2026-08-06 10:00:00',
            'voided_at' => now(),
        ]);

        $this->assertSame('500000.00', app(ReportService::class)->summary($this->period())['cash_income']);
    }

    public function test_appointment_volume_is_grouped_by_status(): void
    {
        Appointment::factory()->count(2)->status(AppointmentStatus::Completed)->create(['starts_at' => '2026-08-10 09:00:00', 'ends_at' => '2026-08-10 10:00:00']);
        Appointment::factory()->status(AppointmentStatus::Cancelled)->create(['starts_at' => '2026-08-11 09:00:00', 'ends_at' => '2026-08-11 10:00:00']);
        Appointment::factory()->status(AppointmentStatus::NoShow)->create(['starts_at' => '2026-08-12 09:00:00', 'ends_at' => '2026-08-12 10:00:00']);
        Appointment::factory()->status(AppointmentStatus::Completed)->create(['starts_at' => '2026-09-12 09:00:00', 'ends_at' => '2026-09-12 10:00:00']);

        $byStatus = app(ReportService::class)->appointmentsByStatus($this->period());

        $this->assertSame(2, $byStatus[AppointmentStatus::Completed->value]);
        $this->assertSame(1, $byStatus[AppointmentStatus::Cancelled->value]);
        $this->assertSame(1, $byStatus[AppointmentStatus::NoShow->value]);
        $this->assertSame(4, array_sum($byStatus));
    }

    public function test_inventory_and_payroll_costs_are_aggregated_in_the_database(): void
    {
        $product = Product::factory()->create();

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovementType::In,
            'quantity' => 10,
            'unit_cost' => 25000,
            'occurred_at' => '2026-08-04 09:00:00',
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovementType::In,
            'quantity' => 4,
            'unit_cost' => 25000,
            'occurred_at' => '2026-09-04 09:00:00',
        ]);

        Payroll::factory()->create(['status' => PayrollStatus::Paid, 'paid_at' => '2026-08-31 18:00:00', 'final_total' => 4000000]);
        Payroll::factory()->create(['status' => PayrollStatus::Finalized, 'final_total' => 9000000]);

        $summary = app(ReportService::class)->summary($this->period());

        $this->assertSame('250000.00', $summary['inventory_cost']);
        $this->assertSame('4000000.00', $summary['payroll_cost']);
    }

    public function test_revenue_breakdowns_only_count_paid_invoices(): void
    {
        $employee = User::factory()->employee()->create(['name' => 'Chị Mai']);

        $paid = Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'paid_at' => '2026-08-20 10:00:00']);
        InvoiceItem::factory()->create([
            'invoice_id' => $paid->id,
            'employee_id' => $employee->id,
            'name' => 'Sơn gel',
            'quantity' => 2,
            'line_total' => 600000,
            'commission_amount' => 60000,
        ]);

        $draft = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);
        InvoiceItem::factory()->create(['invoice_id' => $draft->id, 'name' => 'Sơn gel', 'line_total' => 100000]);

        $reports = app(ReportService::class);

        $byService = $reports->revenueByService($this->period());
        $this->assertCount(1, $byService);
        $this->assertSame('Sơn gel', $byService->first()->service_name);
        $this->assertSame('600000.00', Money::toDecimal(Money::toMinor($byService->first()->revenue_total)));

        $byEmployee = $reports->revenueByEmployee($this->period());
        $this->assertSame('Chị Mai', $byEmployee->first()->employee_name);
        $this->assertSame('60000.00', Money::toDecimal(Money::toMinor($byEmployee->first()->commission_total)));
    }

    public function test_the_period_defaults_to_the_current_month_and_swaps_reversed_dates(): void
    {
        $default = ReportPeriod::fromRequest(Request::create('/'));
        $this->assertSame(now()->startOfMonth()->toDateString(), $default->from->toDateString());
        $this->assertSame(now()->endOfMonth()->toDateString(), $default->to->toDateString());

        $swapped = $this->period('2026-08-31', '2026-08-01');
        $this->assertSame('2026-08-01', $swapped->from->toDateString());
        $this->assertSame('2026-08-31', $swapped->to->toDateString());
    }

    public function test_an_absurd_range_is_capped(): void
    {
        $period = $this->period('2020-01-01', '2030-01-01');

        $this->assertSame(ReportPeriod::MAX_DAYS, (int) $period->from->diffInDays($period->to));
    }

    public function test_the_profit_figure_is_owner_only(): void
    {
        Invoice::factory()->create(['status' => InvoiceStatus::Paid, 'paid_at' => '2026-08-15 10:00:00', 'total' => 500000]);

        $query = ['from' => '2026-08-01', 'to' => '2026-08-31'];

        $this->actingAs(User::factory()->owner()->create())
            ->get(route('admin.reports.index', $query))
            ->assertOk()
            ->assertSee('Lãi gộp ước tính');

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('admin.reports.index', $query))
            ->assertOk()
            ->assertDontSee('Lãi gộp ước tính');
    }

    public function test_the_csv_export_streams_with_a_utf8_bom(): void
    {
        Invoice::factory()->create([
            'number' => 'NEO-EXPORT-1',
            'customer_name' => 'Chị Hoà',
            'status' => InvoiceStatus::Paid,
            'paid_at' => '2026-08-15 10:00:00',
            'total' => 500000,
        ]);

        $response = $this->actingAs(User::factory()->owner()->create())
            ->get(route('admin.reports.export.invoices'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('NEO-EXPORT-1', $content);
        $this->assertStringContainsString('Chị Hoà', $content);
    }
}
