<?php

namespace Tests\Feature\Branch;

use App\Actions\Payrolls\CalculatePayrollAction;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\PayrollPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BranchPayrollVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::factory()->create(['code' => 'CN-PA']);
        $this->branchB = Branch::factory()->create(['code' => 'CN-PB']);
        PayrollPolicy::factory()->create(['attendance_bonus_amount' => 0, 'allowed_absence_days' => 31]);
    }

    private function payrollAt(Branch $branch, string $name): Payroll
    {
        $employee = User::factory()->employee()->withoutBranch()->atBranch($branch)->create(['name' => $name]);

        return app(CalculatePayrollAction::class)->refresh(
            Payroll::factory()->create([
                'employee_id' => $employee->id,
                'paying_branch_id' => $branch->id,
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
            ])
        );
    }

    public function test_a_payroll_manager_only_lists_payrolls_of_their_own_branch(): void
    {
        $this->payrollAt($this->branchA, 'Nhân sự cơ sở A');
        $this->payrollAt($this->branchB, 'Nhân sự cơ sở B');

        $manager = User::factory()->payrollManager()->withoutBranch()->atBranch($this->branchA)->create();

        $this->actingAs($manager)
            ->get(route('admin.payrolls.index'))
            ->assertOk()
            ->assertSee('Nhân sự cơ sở A')
            ->assertDontSee('Nhân sự cơ sở B');
    }

    public function test_an_uncalculated_payroll_stays_with_its_paying_branch(): void
    {
        $employee = User::factory()->employee()->withoutBranch()->atBranch($this->branchB)->create(['name' => 'Chưa tính lương B']);

        Payroll::factory()->create([
            'employee_id' => $employee->id,
            'paying_branch_id' => $this->branchB->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ]);

        $manager = User::factory()->payrollManager()->withoutBranch()->atBranch($this->branchA)->create();

        $this->actingAs($manager)
            ->get(route('admin.payrolls.index'))
            ->assertOk()
            ->assertDontSee('Chưa tính lương B');
    }

    public function test_the_payroll_list_does_not_run_a_query_per_row(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->payrollAt($this->branchA, 'Nhân sự '.$i);
        }

        $owner = User::factory()->owner()->create();

        DB::enableQueryLog();
        $this->actingAs($owner)->get(route('admin.payrolls.index'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(20, $queries, "Danh sách bảng lương chạy {$queries} truy vấn, nghi ngờ lỗi N+1.");
    }

    public function test_the_invoice_list_does_not_run_a_query_per_row(): void
    {
        $owner = User::factory()->owner()->create();

        Invoice::factory()->count(12)->create(['branch_id' => $this->branchA->id]);

        DB::enableQueryLog();
        $this->actingAs($owner)->get(route('admin.invoices.index'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(15, $queries, "Danh sách hóa đơn chạy {$queries} truy vấn, nghi ngờ lỗi N+1.");
    }
}
