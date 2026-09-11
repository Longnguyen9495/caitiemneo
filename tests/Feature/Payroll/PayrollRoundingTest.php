<?php

namespace Tests\Feature\Payroll;

use App\Actions\Payrolls\CalculatePayrollAction;
use App\Enums\PayrollAdjustmentCategory;
use App\Enums\PayrollAdjustmentDirection;
use App\Enums\RoundingRule;
use App\Models\Branch;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPolicy;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The shop always rounds the final take-home UP to the next 1.000 d.
 */
class PayrollRoundingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'CN-R']);

        PayrollPolicy::factory()->create([
            'branch_id' => null,
            'attendance_bonus_amount' => 0,
            'rounding_rule' => RoundingRule::CeilTo1000,
        ]);
    }

    /**
     * Build a payroll whose calculated total is exactly the given amount, by
     * putting it in as a single manual allowance.
     */
    private function payrollTotalling(string $amount): Payroll
    {
        $employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();

        $payroll = Payroll::factory()->create([
            'employee_id' => $employee->id,
            'paying_branch_id' => $this->branch->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ]);

        PayrollAdjustment::query()->create([
            'payroll_id' => $payroll->id,
            'branch_id' => $this->branch->id,
            'category' => PayrollAdjustmentCategory::Allowance,
            'direction' => PayrollAdjustmentDirection::Earning,
            'amount' => $amount,
            'description' => 'Khoản dựng cho kiểm thử làm tròn',
            'is_automatic' => false,
        ]);

        return app(CalculatePayrollAction::class)->refresh($payroll);
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function roundingCases(): array
    {
        return [
            'rounds a part-thousand up' => ['12408250', '12409000.00', '750.00'],
            'rounds one dong above the step' => ['12408001', '12409000.00', '999.00'],
            'rounds one dong below the step' => ['12408999', '12409000.00', '1.00'],
            'leaves an exact thousand alone' => ['12408000', '12408000.00', '0.00'],
        ];
    }

    #[DataProvider('roundingCases')]
    public function test_the_final_total_is_ceiled_to_the_next_thousand(string $amount, string $expectedFinal, string $expectedAdjustment): void
    {
        $payroll = $this->payrollTotalling($amount);

        $this->assertSame($amount.'.00', $payroll->unrounded_final_total);
        $this->assertSame($expectedFinal, $payroll->final_total);
        $this->assertSame($expectedAdjustment, $payroll->rounding_adjustment);
        $this->assertSame(RoundingRule::CeilTo1000, $payroll->rounding_rule);
    }

    public function test_a_zero_payroll_stays_at_zero(): void
    {
        $employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();

        $payroll = app(CalculatePayrollAction::class)->refresh(
            Payroll::factory()->create([
                'employee_id' => $employee->id,
                'paying_branch_id' => $this->branch->id,
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
            ])
        );

        $this->assertSame('0.00', $payroll->unrounded_final_total);
        $this->assertSame('0.00', $payroll->final_total);
        $this->assertSame('0.00', $payroll->rounding_adjustment);
    }

    public function test_the_final_total_is_always_a_whole_thousand(): void
    {
        foreach (['1', '499', '500', '999', '1000', '1001', '7654321'] as $amount) {
            $payroll = $this->payrollTotalling($amount);

            $this->assertSame(0, Money::toMinor($payroll->final_total) % (1000 * 100));
            $this->assertGreaterThanOrEqual(
                Money::toMinor($payroll->unrounded_final_total),
                Money::toMinor($payroll->final_total),
            );
            $this->assertLessThan(1000 * 100, Money::toMinor($payroll->rounding_adjustment));
        }
    }

    public function test_recalculating_does_not_stack_the_rounding(): void
    {
        $payroll = $this->payrollTotalling('12408250');

        app(CalculatePayrollAction::class)->refresh($payroll);
        app(CalculatePayrollAction::class)->refresh($payroll->fresh());

        $payroll->refresh();

        $this->assertSame('12408250.00', $payroll->unrounded_final_total);
        $this->assertSame('12409000.00', $payroll->final_total);
        $this->assertSame('750.00', $payroll->rounding_adjustment);
    }

    public function test_components_are_not_rounded_before_they_are_added(): void
    {
        // Three amounts that each carry a part-thousand tail. Rounding each one
        // would give 3.000 d of padding; rounding only the sum gives 1 d.
        $employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();

        $payroll = Payroll::factory()->create([
            'employee_id' => $employee->id,
            'paying_branch_id' => $this->branch->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ]);

        foreach (['100333', '200333', '300333'] as $index => $amount) {
            PayrollAdjustment::query()->create([
                'payroll_id' => $payroll->id,
                'branch_id' => $this->branch->id,
                'category' => PayrollAdjustmentCategory::Allowance,
                'direction' => PayrollAdjustmentDirection::Earning,
                'amount' => $amount,
                'description' => 'Khoản '.$index,
                'is_automatic' => false,
            ]);
        }

        $payroll = app(CalculatePayrollAction::class)->refresh($payroll);

        $this->assertSame('600999.00', $payroll->unrounded_final_total);
        $this->assertSame('601000.00', $payroll->final_total);
        $this->assertSame('1.00', $payroll->rounding_adjustment);
    }

    public function test_a_client_cannot_post_its_own_final_total_or_rounding(): void
    {
        $owner = User::factory()->owner()->create();
        $payroll = $this->payrollTotalling('12408250');

        $this->actingAs($owner)->patch(route('admin.payrolls.update', $payroll), [
            'final_total' => 1,
            'rounding_adjustment' => 999999,
            'unrounded_final_total' => 1,
            'calculated_total' => 1,
        ])->assertRedirect();

        $payroll->refresh();

        $this->assertSame('12409000.00', $payroll->final_total);
        $this->assertSame('750.00', $payroll->rounding_adjustment);
    }
}
