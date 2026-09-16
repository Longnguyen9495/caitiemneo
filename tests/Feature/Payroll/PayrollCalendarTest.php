<?php

namespace Tests\Feature\Payroll;

use App\Enums\AttendanceStatus;
use App\Enums\PayrollStatus;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected User $owner;

    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->owner = User::factory()->owner()->create();
        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
    }

    public function test_the_payroll_show_page_renders_the_reconciliation_calendar(): void
    {
        $payroll = Payroll::factory()->create([
            'employee_id' => $this->employee->id,
            'paying_branch_id' => $this->branch->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'status' => PayrollStatus::Draft,
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('admin.payrolls.show', $payroll))
            ->assertOk();

        $response->assertSee('Lịch đối soát chấm công');
    }

    public function test_the_calendar_shows_attendance_within_the_payroll_period(): void
    {
        $payroll = Payroll::factory()->create([
            'employee_id' => $this->employee->id,
            'paying_branch_id' => $this->branch->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'status' => PayrollStatus::Draft,
        ]);

        // Record inside period
        AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-08-05',
            'shift_name' => 'Ca sáng',
            'shift_value' => 1,
            'status' => AttendanceStatus::Present,
        ]);

        // Record outside period — should not appear in calendar
        AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-07-31',
            'shift_name' => 'Ca sáng',
            'shift_value' => 1,
            'status' => AttendanceStatus::Present,
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('admin.payrolls.show', $payroll))
            ->assertOk();

        $response->assertSee('Ca sáng');
    }

    public function test_the_calendar_shows_locked_status_for_finalized_payroll(): void
    {
        $payroll = Payroll::factory()->create([
            'employee_id' => $this->employee->id,
            'paying_branch_id' => $this->branch->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'status' => PayrollStatus::Finalized,
            'finalized_at' => now(),
        ]);

        AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-08-05',
            'shift_name' => 'Ca sáng',
            'shift_value' => 1,
            'status' => AttendanceStatus::Present,
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('admin.payrolls.show', $payroll))
            ->assertOk();

        $response->assertSee('Kỳ lương đã chốt');
        $response->assertSee('snapshot đã khóa');
    }

    public function test_the_calendar_shows_draft_warning(): void
    {
        $payroll = Payroll::factory()->create([
            'employee_id' => $this->employee->id,
            'paying_branch_id' => $this->branch->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'status' => PayrollStatus::Draft,
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('admin.payrolls.show', $payroll))
            ->assertOk();

        $response->assertSee('Dữ liệu chấm công hiện tại');
    }

    public function test_the_calendar_title_shows_period_range(): void
    {
        $payroll = Payroll::factory()->create([
            'employee_id' => $this->employee->id,
            'paying_branch_id' => $this->branch->id,
            'period_start' => '2026-08-10',
            'period_end' => '2026-08-20',
            'status' => PayrollStatus::Draft,
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('admin.payrolls.show', $payroll))
            ->assertOk();

        $response->assertSee('Kỳ lương');
        $response->assertSee('10/08/2026');
        $response->assertSee('20/08/2026');
    }
}
