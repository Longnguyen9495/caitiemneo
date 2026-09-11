<?php

namespace Tests\Feature\Branch;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class BranchReportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::factory()->create(['code' => 'CN-RA', 'name' => 'Cơ sở A']);
        $this->branchB = Branch::factory()->create(['code' => 'CN-RB', 'name' => 'Cơ sở B']);

        $this->revenue($this->branchA, 3000000);
        $this->revenue($this->branchB, 5000000);

        CashTransaction::factory()->income()->create(['branch_id' => $this->branchA->id, 'amount' => 1000000, 'occurred_at' => '2026-08-05 10:00:00']);
        CashTransaction::factory()->create(['branch_id' => $this->branchB->id, 'amount' => 400000, 'occurred_at' => '2026-08-06 10:00:00']);
    }

    private function revenue(Branch $branch, int $total): void
    {
        $invoice = Invoice::factory()->create([
            'branch_id' => $branch->id,
            'status' => InvoiceStatus::Paid,
            'paid_at' => '2026-08-10 10:00:00',
            'total' => $total,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'line_total' => $total,
            'commission_amount' => (int) ($total * 0.15),
        ]);
    }

    private function period(): ReportPeriod
    {
        return ReportPeriod::fromRequest(Request::create('/', 'GET', ['from' => '2026-08-01', 'to' => '2026-08-31']));
    }

    public function test_a_single_branch_summary_only_counts_that_branch(): void
    {
        $reports = app(ReportService::class);

        $this->assertSame('3000000.00', $reports->summary($this->period(), [$this->branchA->id])['revenue']);
        $this->assertSame('5000000.00', $reports->summary($this->period(), [$this->branchB->id])['revenue']);
    }

    public function test_the_company_wide_summary_is_the_sum_of_both_branches(): void
    {
        $summary = app(ReportService::class)->summary($this->period(), [$this->branchA->id, $this->branchB->id]);

        $this->assertSame('8000000.00', $summary['revenue']);
        $this->assertSame('1000000.00', $summary['cash_income']);
        $this->assertSame('400000.00', $summary['cash_expense']);
        $this->assertSame('600000.00', $summary['cash_net']);
    }

    public function test_a_manager_sees_only_their_own_branch_figures(): void
    {
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branchA)->create();

        $this->actingAs($manager)
            ->get(route('admin.reports.index', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk()
            ->assertSee('3.000.000')
            ->assertDontSee('8.000.000');
    }

    public function test_an_owner_can_compare_both_branches(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get(route('admin.reports.index', ['from' => '2026-08-01', 'to' => '2026-08-31', 'branch' => 'all']))
            ->assertOk()
            ->assertSee('So sánh chi nhánh')
            ->assertSee('Cơ sở A')
            ->assertSee('Cơ sở B');
    }

    public function test_the_csv_export_never_leaks_another_branch(): void
    {
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branchA)->create();

        Invoice::factory()->create(['branch_id' => $this->branchA->id, 'number' => 'NEO-MINE']);
        Invoice::factory()->create(['branch_id' => $this->branchB->id, 'number' => 'NEO-THEIRS']);

        $content = $this->actingAs($manager)
            ->get(route('admin.reports.export.invoices'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('NEO-MINE', $content);
        $this->assertStringNotContainsString('NEO-THEIRS', $content);
    }

    public function test_cash_totals_are_reported_per_branch(): void
    {
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branchB)->create();

        $this->actingAs($manager)
            ->get(route('admin.cash.index'))
            ->assertOk()
            ->assertSee('400.000')
            ->assertDontSee('1.000.000');
    }

    public function test_paying_an_invoice_books_the_income_in_its_own_branch(): void
    {
        $owner = User::factory()->owner()->create();
        $invoice = Invoice::factory()->create(['branch_id' => $this->branchB->id]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_price' => 250000, 'line_total' => 250000]);

        $this->actingAs($owner)->post(route('admin.invoices.pay', $invoice), ['payment_method' => PaymentMethod::Cash->value]);

        $transaction = CashTransaction::query()->where('invoice_id', $invoice->id)->firstOrFail();

        $this->assertSame($this->branchB->id, $transaction->branch_id);
    }
}
