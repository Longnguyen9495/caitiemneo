<?php

namespace Tests\Feature\Payroll;

use App\Actions\Payrolls\CalculatePayrollAction;
use App\Enums\InvoiceStatus;
use App\Enums\WorkContext;
use App\Models\Branch;
use App\Models\DailyKpiResult;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payroll;
use App\Models\PayrollPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Daily revenue KPI: 1.000.000 d pays 50.000 d, 1.700.000 d pays 100.000 d,
 * and the two bands never stack.
 */
class DailyKpiTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'CN-K']);

        PayrollPolicy::factory()->withStandardKpiTiers()->create([
            'branch_id' => null,
            'attendance_bonus_amount' => 0,
            'allowed_absence_days' => 31,
        ]);

        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
    }

    private function revenueOn(string $paidAt, int $amount, InvoiceStatus $status = InvoiceStatus::Paid, ?Branch $branch = null): void
    {
        $invoice = Invoice::factory()->create([
            'branch_id' => ($branch ?? $this->branch)->id,
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::Paid ? $paidAt : null,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'employee_id' => $this->employee->id,
            'work_context' => WorkContext::Regular,
            'line_total' => $amount,
        ]);
    }

    private function calculate(): Payroll
    {
        $payroll = Payroll::factory()->create([
            'employee_id' => $this->employee->id,
            'paying_branch_id' => $this->branch->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ]);

        return app(CalculatePayrollAction::class)->refresh($payroll);
    }

    /** @return array<string, array{0: int, 1: string}> */
    public static function revenueBands(): array
    {
        return [
            'below the first band pays nothing' => [999999, '0.00'],
            'exactly one million pays the first band' => [1000000, '50000.00'],
            'inside the first band pays the first band' => [1500000, '50000.00'],
            'just below the second band pays the first' => [1699999, '50000.00'],
            'exactly the second threshold pays the second band' => [1700000, '100000.00'],
            'above the second band still pays the second' => [5000000, '100000.00'],
        ];
    }

    #[DataProvider('revenueBands')]
    public function test_a_day_pays_only_the_highest_band_it_reaches(int $revenue, string $expectedReward): void
    {
        $this->revenueOn('2026-08-10 15:00:00', $revenue);

        $payroll = $this->calculate();

        $this->assertSame($expectedReward, $payroll->daily_kpi_bonus);
    }

    public function test_each_day_is_scored_on_its_own(): void
    {
        $this->revenueOn('2026-08-10 10:00:00', 1200000);
        $this->revenueOn('2026-08-11 10:00:00', 1800000);
        $this->revenueOn('2026-08-12 10:00:00', 500000);

        $payroll = $this->calculate();

        $this->assertSame('150000.00', $payroll->daily_kpi_bonus);
        $this->assertSame(3, DailyKpiResult::query()->where('payroll_id', $payroll->id)->count());
    }

    public function test_revenue_of_one_day_is_summed_before_the_band_is_chosen(): void
    {
        $this->revenueOn('2026-08-10 09:00:00', 900000);
        $this->revenueOn('2026-08-10 18:00:00', 900000);

        // 1.800.000 d on the day, so the higher band applies once.
        $this->assertSame('100000.00', $this->calculate()->daily_kpi_bonus);
    }

    public function test_unpaid_and_cancelled_invoices_do_not_count(): void
    {
        $this->revenueOn('2026-08-10 10:00:00', 2000000, InvoiceStatus::Draft);
        $this->revenueOn('2026-08-11 10:00:00', 2000000, InvoiceStatus::Cancelled);

        $this->assertSame('0.00', $this->calculate()->daily_kpi_bonus);
    }

    public function test_revenue_outside_the_period_does_not_count(): void
    {
        $this->revenueOn('2026-07-31 23:00:00', 2000000);
        $this->revenueOn('2026-09-01 01:00:00', 2000000);

        $this->assertSame('0.00', $this->calculate()->daily_kpi_bonus);
    }

    public function test_kpi_is_scored_per_branch(): void
    {
        $other = Branch::factory()->create(['code' => 'CN-K2']);
        $this->employee->branchAssignments()->create([
            'branch_id' => $other->id,
            'is_primary' => false,
            'starts_on' => '2000-01-01',
        ]);

        // 900.000 d at each shop on the same day: neither reaches the band.
        $this->revenueOn('2026-08-10 10:00:00', 900000);
        $this->revenueOn('2026-08-10 11:00:00', 900000, InvoiceStatus::Paid, $other);

        $payroll = $this->calculate();

        $this->assertSame('0.00', $payroll->daily_kpi_bonus);
        $this->assertSame(2, DailyKpiResult::query()->where('payroll_id', $payroll->id)->count());
    }

    public function test_the_kpi_snapshot_records_the_band_and_the_policy(): void
    {
        $this->revenueOn('2026-08-10 10:00:00', 1750000);

        $payroll = $this->calculate();
        $result = DailyKpiResult::query()->where('payroll_id', $payroll->id)->firstOrFail();

        $this->assertSame('1750000.00', $result->eligible_revenue);
        $this->assertSame('100000.00', $result->reward_amount);
        $this->assertNotNull($result->achieved_tier_id);
        $this->assertNotNull($result->source_policy_id);
        $this->assertSame($this->branch->id, $result->branch_id);
    }
}
