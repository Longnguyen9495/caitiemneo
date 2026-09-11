<?php

namespace Tests\Feature\Payroll;

use App\Actions\Payrolls\CalculatePayrollAction;
use App\Enums\PayrollAdjustmentCategory;
use App\Enums\PayrollAdjustmentDirection;
use App\Models\Branch;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollVarianceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'CN-V']);
        PayrollPolicy::factory()->create(['attendance_bonus_amount' => 0, 'allowed_absence_days' => 31]);
        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
    }

    private function payroll(): Payroll
    {
        $payroll = Payroll::factory()->create([
            'employee_id' => $this->employee->id,
            'paying_branch_id' => $this->branch->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ]);

        PayrollAdjustment::query()->create([
            'payroll_id' => $payroll->id,
            'branch_id' => $this->branch->id,
            'category' => PayrollAdjustmentCategory::Allowance,
            'direction' => PayrollAdjustmentDirection::Earning,
            'amount' => 5000500,
            'description' => 'Khoản nền',
            'is_automatic' => false,
        ]);

        return app(CalculatePayrollAction::class)->refresh($payroll);
    }

    public function test_an_override_needs_a_reason_and_an_approver(): void
    {
        $owner = User::factory()->owner()->create();
        $payroll = $this->payroll();

        $this->actingAs($owner)
            ->from(route('admin.payrolls.show', $payroll))
            ->post(route('admin.payrolls.variance', $payroll), ['approved_manual_adjustment' => 100000])
            ->assertSessionHasErrors('variance_reason');

        $this->assertSame('0.00', $payroll->fresh()->approved_manual_adjustment);
    }

    public function test_an_approved_override_is_recorded_and_rounded_once(): void
    {
        $owner = User::factory()->owner()->create();
        $payroll = $this->payroll();

        $this->assertSame('5000500.00', $payroll->calculated_total);
        $this->assertSame('5001000.00', $payroll->final_total);

        $this->actingAs($owner)->post(route('admin.payrolls.variance', $payroll), [
            'approved_manual_adjustment' => 200000,
            'variance_reason' => 'Chốt thêm theo thỏa thuận với nhân viên',
        ])->assertRedirect();

        $payroll->refresh();

        $this->assertSame('5000500.00', $payroll->calculated_total);
        $this->assertSame('200000.00', $payroll->approved_manual_adjustment);
        $this->assertSame('5200500.00', $payroll->unrounded_final_total);
        $this->assertSame('5201000.00', $payroll->final_total);
        $this->assertSame('500.00', $payroll->rounding_adjustment);
        $this->assertSame($owner->id, $payroll->approved_by);
        $this->assertNotNull($payroll->approved_at);
        $this->assertSame('Chốt thêm theo thỏa thuận với nhân viên', $payroll->variance_reason);
    }

    public function test_only_the_owner_may_approve_a_variance(): void
    {
        $manager = User::factory()->payrollManager()->withoutBranch()->atBranch($this->branch)->create();
        $payroll = $this->payroll();

        $this->actingAs($manager)
            ->post(route('admin.payrolls.variance', $payroll), [
                'approved_manual_adjustment' => 100000,
                'variance_reason' => 'Thử',
            ])
            ->assertForbidden();
    }

    public function test_a_negative_result_is_floored_at_zero_before_rounding(): void
    {
        $owner = User::factory()->owner()->create();
        $payroll = $this->payroll();

        $this->actingAs($owner)->post(route('admin.payrolls.variance', $payroll), [
            'approved_manual_adjustment' => -9000000,
            'variance_reason' => 'Trừ toàn bộ do tạm ứng vượt mức',
        ]);

        $payroll->refresh();

        $this->assertSame('0.00', $payroll->unrounded_final_total);
        $this->assertSame('0.00', $payroll->final_total);
        $this->assertSame('0.00', $payroll->rounding_adjustment);
    }

    public function test_a_manual_adjustment_requires_a_description(): void
    {
        $owner = User::factory()->owner()->create();
        $payroll = $this->payroll();

        $this->actingAs($owner)
            ->from(route('admin.payrolls.show', $payroll))
            ->post(route('admin.payrolls.adjustments.store', $payroll), [
                'category' => PayrollAdjustmentCategory::Penalty->value,
                'direction' => PayrollAdjustmentDirection::Deduction->value,
                'amount' => 50000,
            ])
            ->assertSessionHasErrors('description');
    }

    public function test_an_engine_owned_category_cannot_be_entered_by_hand(): void
    {
        $owner = User::factory()->owner()->create();
        $payroll = $this->payroll();

        $this->actingAs($owner)
            ->from(route('admin.payrolls.show', $payroll))
            ->post(route('admin.payrolls.adjustments.store', $payroll), [
                'category' => PayrollAdjustmentCategory::DailyKpiBonus->value,
                'direction' => PayrollAdjustmentDirection::Earning->value,
                'amount' => 50000,
                'description' => 'Tự thêm KPI',
            ])
            ->assertSessionHasErrors('category');
    }

    public function test_a_manual_deduction_lowers_the_take_home(): void
    {
        $owner = User::factory()->owner()->create();
        $payroll = $this->payroll();

        $this->actingAs($owner)->post(route('admin.payrolls.adjustments.store', $payroll), [
            'category' => PayrollAdjustmentCategory::SalaryAdvance->value,
            'direction' => PayrollAdjustmentDirection::Deduction->value,
            'amount' => 500000,
            'description' => 'Tạm ứng giữa tháng',
            'branch_id' => $this->branch->id,
        ])->assertRedirect();

        $payroll->refresh();

        $this->assertSame('4500500.00', $payroll->calculated_total);
        $this->assertSame('4501000.00', $payroll->final_total);
    }
}
