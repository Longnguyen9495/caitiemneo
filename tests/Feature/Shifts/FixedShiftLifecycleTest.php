<?php

namespace Tests\Feature\Shifts;

use App\Models\Branch;
use App\Models\EmployeeFixedShift;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Một ca cố định phải kết thúc được.
 *
 * Trước đây màn hình chỉ có đường vào: không route sửa, không route xóa, và
 * quy tắc chống chồng lấn chặn luôn mọi ca mới của cùng nhân viên. Một dòng gõ
 * nhầm là nhân viên đó mang ca cố định sai mãi mãi.
 */
class FixedShiftLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    private User $employee;

    private WorkShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-15 09:00:00'));

        $this->branch = Branch::factory()->create();
        $this->manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();
        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
        $this->shift = WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '17:00:00')->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_manager_can_end_a_running_fixed_shift(): void
    {
        $fixedShift = $this->fixedShift('2026-10-01');

        $this->actingAs($this->manager)
            ->from(route('admin.employee-shift-plans.index'))
            ->patch(route('admin.employee-shift-plans.fixed-shifts.end', $fixedShift), ['effective_to' => '2026-10-20'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-10-20', $fixedShift->fresh()->effective_to->toDateString());
    }

    /** Đóng xong thì chỗ trống mở ra, và ca cố định mới vào được. */
    public function test_ending_one_makes_room_for_the_next(): void
    {
        $fixedShift = $this->fixedShift('2026-10-01');
        $evening = WorkShift::factory()->atBranch($this->branch)->spanning('14:00:00', '22:00:00')->create();

        $this->actingAs($this->manager)
            ->post(route('admin.employee-shift-plans.fixed-shifts.store'), [
                'employee_id' => $this->employee->id,
                'work_shift_id' => $evening->id,
                'effective_from' => '2026-11-01',
            ])
            ->assertSessionHasErrors('effective_from');

        $this->actingAs($this->manager)
            ->patch(route('admin.employee-shift-plans.fixed-shifts.end', $fixedShift), ['effective_to' => '2026-10-31']);

        $this->actingAs($this->manager)
            ->post(route('admin.employee-shift-plans.fixed-shifts.store'), [
                'employee_id' => $this->employee->id,
                'work_shift_id' => $evening->id,
                'effective_from' => '2026-11-01',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, EmployeeFixedShift::query()->count());
    }

    public function test_an_end_date_before_the_start_is_refused(): void
    {
        $fixedShift = $this->fixedShift('2026-10-01');

        $this->actingAs($this->manager)
            ->from(route('admin.employee-shift-plans.index'))
            ->patch(route('admin.employee-shift-plans.fixed-shifts.end', $fixedShift), ['effective_to' => '2026-09-30'])
            ->assertSessionHasErrors('effective_to');

        $this->assertNull($fixedShift->fresh()->effective_to);
    }

    /** Chưa tới ngày hiệu lực thì chưa sinh ra gì, nên xóa hẳn được. */
    public function test_a_plan_that_has_not_started_can_be_deleted(): void
    {
        $fixedShift = $this->fixedShift('2026-12-01');

        $this->actingAs($this->manager)
            ->delete(route('admin.employee-shift-plans.fixed-shifts.destroy', $fixedShift))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($fixedShift);
    }

    /** Đã chạy qua ngày thật thì là lịch sử: đóng lại, không xóa. */
    public function test_a_plan_already_in_force_cannot_be_deleted(): void
    {
        $fixedShift = $this->fixedShift('2026-10-01');

        $this->actingAs($this->manager)
            ->from(route('admin.employee-shift-plans.index'))
            ->delete(route('admin.employee-shift-plans.fixed-shifts.destroy', $fixedShift))
            ->assertSessionHasErrors('effective_to');

        $this->assertModelExists($fixedShift);
    }

    public function test_a_manager_from_another_branch_cannot_end_it(): void
    {
        $fixedShift = $this->fixedShift('2026-10-01');
        $outsider = User::factory()->manager()->withoutBranch()->atBranch(Branch::factory()->create())->create();

        $this->actingAs($outsider)
            ->patch(route('admin.employee-shift-plans.fixed-shifts.end', $fixedShift), ['effective_to' => '2026-10-20'])
            ->assertForbidden();

        $this->assertNull($fixedShift->fresh()->effective_to);
    }

    public function test_an_employee_cannot_end_their_own_fixed_shift(): void
    {
        $fixedShift = $this->fixedShift('2026-10-01');

        $this->actingAs($this->employee)
            ->patch(route('admin.employee-shift-plans.fixed-shifts.end', $fixedShift), ['effective_to' => '2026-10-20'])
            ->assertForbidden();
    }

    public function test_the_plan_table_offers_a_way_to_end_an_open_ended_plan(): void
    {
        $this->fixedShift('2026-10-01');

        $this->actingAs($this->manager)
            ->get(route('admin.employee-shift-plans.index', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee('Kết thúc từ')
            ->assertSee(route('admin.employee-shift-plans.fixed-shifts.end', EmployeeFixedShift::query()->sole()), false);
    }

    private function fixedShift(string $effectiveFrom): EmployeeFixedShift
    {
        return EmployeeFixedShift::factory()->create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'work_shift_id' => $this->shift->id,
            'effective_from' => $effectiveFrom,
        ]);
    }
}
