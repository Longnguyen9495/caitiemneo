<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Enums\AttendanceStatus;
use App\Enums\PayrollStatus;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1 tests for payroll calendar: read-only detail panel, no mutation links.
 */
class PayrollCalendarReadOnlyTest extends TestCase
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

    public function test_payroll_calendar_day_can_open_details_read_only(): void
    {
        $payroll = Payroll::factory()->create([
            'employee_id' => $this->employee->id,
            'paying_branch_id' => $this->branch->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'status' => PayrollStatus::Draft,
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

        $html = $response->getContent() ?: '';

        // The day cell should be a button with accessible label (can open detail)
        $this->assertMatchesRegularExpression(
            '/data-cal-date="2026-08-05"/',
            $html,
            'Day cell should have data-cal-date for detail opening'
        );

        // But should NOT contain edit/create/delete links
        $this->assertStringNotContainsString('Sửa', $html);
        $this->assertStringNotContainsString('Xóa', $html);
        $this->assertStringNotContainsString('Ghi ca', $html);
    }

    public function test_payroll_calendar_does_not_recalculate_on_view(): void
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

        // Payroll should not be mutated just by viewing
        $payroll->refresh();
        $this->assertSame('draft', $payroll->status->value);
        $this->assertSame(0, (int) $payroll->total);
    }

    public function test_payroll_toolbar_does_not_render_empty_href(): void
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

        $html = $response->getContent() ?: '';

        // Should not have href="" (empty href on anchor)
        $this->assertDoesNotMatchRegularExpression('/href=["\']\s*["\']/', $html, 'Empty href found in payroll calendar');
    }
}
