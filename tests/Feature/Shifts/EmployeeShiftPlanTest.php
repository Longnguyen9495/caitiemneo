<?php

namespace Tests\Feature\Shifts;

use App\Actions\Shifts\ConfigureEmployeeFixedShiftAction;
use App\Actions\Shifts\GenerateMonthlyFixedShiftScheduleAction;
use App\Actions\Shifts\ManageShiftRequestAction;
use App\Enums\LeaveEntitlement;
use App\Enums\PayrollStatus;
use App\Models\Branch;
use App\Models\EmployeeFixedShift;
use App\Models\MonthlyPaidLeaveDay;
use App\Models\Payroll;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmployeeShiftPlanTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    private User $employee;

    private WorkShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();
        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
        $this->shift = WorkShift::factory()->atBranch($this->branch)->create();
    }

    public function test_a_manager_can_open_the_shift_plan_page_for_the_active_branch(): void
    {
        $this->actingAs($this->manager)
            ->get(route('admin.employee-shift-plans.index', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee('Ca cố định')
            ->assertDontSee('Xếp 2 ngày nghỉ hưởng lương')
            ->assertSee($this->employee->name);
    }

    public function test_the_fixed_shift_endpoint_uses_the_active_branch_not_a_posted_branch(): void
    {
        $otherBranch = Branch::factory()->create();

        $this->actingAs($this->manager)
            ->post(route('admin.employee-shift-plans.fixed-shifts.store'), [
                'branch_id' => $otherBranch->id,
                'employee_id' => $this->employee->id,
                'work_shift_id' => $this->shift->id,
                'effective_from' => '2026-10-01',
            ])
            ->assertRedirect();

        $this->assertSame($this->branch->id, EmployeeFixedShift::query()->sole()->branch_id);
    }

    public function test_a_fixed_shift_rejects_an_overlapping_effective_window(): void
    {
        $action = app(ConfigureEmployeeFixedShiftAction::class);

        $action->handle($this->manager, $this->employee, $this->branch, $this->shift, '2026-10-01');

        try {
            $action->handle($this->manager, $this->employee, $this->branch, $this->shift, '2026-10-15', '2026-10-31');
            $this->fail('Expected an overlapping fixed-shift window to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('effective_from', $exception->errors());
        }

        $this->assertSame(1, EmployeeFixedShift::query()->count());
    }

    public function test_monthly_generation_is_idempotent_and_preserves_an_existing_assignment(): void
    {
        EmployeeFixedShift::factory()->create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'work_shift_id' => $this->shift->id,
            'effective_from' => '2026-10-01',
        ]);
        ShiftAssignment::factory()
            ->forEmployee($this->employee)
            ->atBranch($this->branch)
            ->usingShift($this->shift)
            ->on('2026-10-05')
            ->create(['note' => 'Lịch thủ công cần được giữ lại.']);

        $action = app(GenerateMonthlyFixedShiftScheduleAction::class);
        $first = $action->handle($this->manager, $this->branch, '2026-10');
        $second = $action->handle($this->manager, $this->branch, '2026-10');

        $this->assertSame(30, $first['created']);
        $this->assertSame(1, $first['skipped']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(31, $second['skipped']);
        $this->assertSame('Lịch thủ công cần được giữ lại.', ShiftAssignment::query()->whereDate('work_date', '2026-10-05')->sole()->note);
    }

    public function test_first_two_approved_leave_requests_in_a_month_are_paid_without_manual_scheduling(): void
    {
        $action = app(ManageShiftRequestAction::class);
        $entitlements = collect(['2026-10-10', '2026-10-20', '2026-10-25'])
            ->map(function (string $date) use ($action): LeaveEntitlement {
                $assignment = ShiftAssignment::factory()
                    ->forEmployee($this->employee)
                    ->atBranch($this->branch)
                    ->usingShift($this->shift)
                    ->on($date)
                    ->create();

                $request = $action->createLeave($this->employee, $assignment->id);

                return $action->decide($this->manager, $request, true)->leave_entitlement;
            });

        $this->assertSame([
            LeaveEntitlement::Paid,
            LeaveEntitlement::Paid,
            LeaveEntitlement::Unpaid,
        ], $entitlements->all());
        $this->assertSame(['2026-10-10', '2026-10-20'], MonthlyPaidLeaveDay::query()
            ->orderBy('leave_date')
            ->pluck('leave_date')
            ->map(fn ($date) => $date->toDateString())
            ->all());
    }

    public function test_approving_leave_in_a_finalized_payroll_period_is_rejected(): void
    {
        $assignment = ShiftAssignment::factory()
            ->forEmployee($this->employee)
            ->atBranch($this->branch)
            ->usingShift($this->shift)
            ->on('2026-10-10')
            ->create();
        $request = app(ManageShiftRequestAction::class)->createLeave($this->employee, $assignment->id);
        Payroll::factory()->create([
            'employee_id' => $this->employee->id,
            'paying_branch_id' => $this->branch->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'status' => PayrollStatus::Finalized,
        ]);

        $this->expectException(ValidationException::class);

        app(ManageShiftRequestAction::class)->decide($this->manager, $request, true);
    }

    /**
     * Một ngày nghỉ vẫn là một ngày, kể cả khi hôm đó xếp hai ca.
     *
     * `monthly_paid_leave_days` khóa duy nhất theo (nhân viên, ngày), nên đơn
     * thứ hai của cùng ngày trước đây làm vỡ ràng buộc và trả về lỗi 500 thay
     * vì duyệt được đơn.
     */
    public function test_two_shifts_on_one_day_consume_a_single_paid_leave_day(): void
    {
        $evening = WorkShift::factory()->atBranch($this->branch)->spanning('18:00:00', '22:00:00')->create();
        $action = app(ManageShiftRequestAction::class);

        $entitlements = collect([$this->shift, $evening])
            ->map(function (WorkShift $shift) use ($action): LeaveEntitlement {
                $assignment = ShiftAssignment::factory()
                    ->forEmployee($this->employee)
                    ->atBranch($this->branch)
                    ->usingShift($shift)
                    ->on('2026-10-10')
                    ->create();

                return $action->decide($this->manager, $action->createLeave($this->employee, $assignment->id), true)->leave_entitlement;
            });

        $this->assertSame([LeaveEntitlement::Paid, LeaveEntitlement::Paid], $entitlements->all());
        $this->assertSame(1, MonthlyPaidLeaveDay::query()->count());
    }

    /** Hạn mức ngày nghỉ hưởng lương đọc từ cấu hình chứ không nằm cứng trong code. */
    public function test_the_paid_leave_allowance_comes_from_configuration(): void
    {
        config(['attendance.paid_leave_days_per_month' => 1]);

        $action = app(ManageShiftRequestAction::class);
        $entitlements = collect(['2026-10-10', '2026-10-20'])
            ->map(function (string $date) use ($action): LeaveEntitlement {
                $assignment = ShiftAssignment::factory()
                    ->forEmployee($this->employee)
                    ->atBranch($this->branch)
                    ->usingShift($this->shift)
                    ->on($date)
                    ->create();

                return $action->decide($this->manager, $action->createLeave($this->employee, $assignment->id), true)->leave_entitlement;
            });

        $this->assertSame([LeaveEntitlement::Paid, LeaveEntitlement::Unpaid], $entitlements->all());
    }
}
