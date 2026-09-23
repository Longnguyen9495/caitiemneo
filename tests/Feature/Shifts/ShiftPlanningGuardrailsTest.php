<?php

namespace Tests\Feature\Shifts;

use App\Enums\PayrollStatus;
use App\Models\Branch;
use App\Models\EmployeeFixedShift;
use App\Models\Payroll;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Các lằn ranh của việc xếp ca mà trước đây không ai canh.
 *
 * Mỗi bài ở đây tương ứng một lỗi đã dựng lại được: người đã nghỉ vẫn xếp được
 * ca, tháng lương đã chốt vẫn sửa được lịch, một ca cố định hỏng nuốt mất kết
 * quả của cả tháng, và tham số ngày sai làm sập trang.
 */
class ShiftPlanningGuardrailsTest extends TestCase
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
        $this->shift = WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '17:00:00')->create();
    }

    public function test_a_deactivated_account_cannot_be_rostered(): void
    {
        $this->employee->update(['is_active' => false]);

        $this->actingAs($this->manager)
            ->from(route('admin.shift-schedule.index'))
            ->post(route('admin.shift-schedule.store'), [
                'employee_id' => $this->employee->id,
                'work_shift_id' => $this->shift->id,
                'work_date' => '2026-10-05',
            ])
            ->assertSessionHasErrors('employee_id');

        $this->assertSame(0, ShiftAssignment::query()->count());
    }

    public function test_a_shift_cannot_be_added_inside_a_finalized_payroll_period(): void
    {
        $this->lockPayrollForOctober();

        $this->actingAs($this->manager)
            ->from(route('admin.shift-schedule.index'))
            ->post(route('admin.shift-schedule.store'), [
                'employee_id' => $this->employee->id,
                'work_shift_id' => $this->shift->id,
                'work_date' => '2026-10-05',
            ])
            ->assertSessionHasErrors('work_date');

        $this->assertSame(0, ShiftAssignment::query()->count());
    }

    public function test_a_shift_cannot_be_removed_from_a_finalized_payroll_period(): void
    {
        $assignment = ShiftAssignment::factory()
            ->forEmployee($this->employee)
            ->atBranch($this->branch)
            ->usingShift($this->shift)
            ->on('2026-10-05')
            ->create();

        $this->lockPayrollForOctober();

        $this->actingAs($this->manager)
            ->from(route('admin.shift-schedule.index'))
            ->delete(route('admin.shift-schedule.destroy', $assignment))
            ->assertSessionHasErrors('work_shift_id');

        $this->assertModelExists($assignment);
    }

    /**
     * Trước đây một ca cố định trỏ vào template đã tắt làm đứt cả vòng lặp:
     * 31 ca đã ghi vẫn nằm lại trong cơ sở dữ liệu còn người bấm chỉ thấy lỗi.
     */
    public function test_one_unusable_fixed_shift_does_not_sink_the_rest_of_the_month(): void
    {
        $other = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
        $retiredShift = WorkShift::factory()->atBranch($this->branch)->spanning('18:00:00', '22:00:00')->inactive()->create();

        EmployeeFixedShift::factory()->create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'work_shift_id' => $this->shift->id,
            'effective_from' => '2026-10-01',
        ]);
        EmployeeFixedShift::factory()->create([
            'employee_id' => $other->id,
            'branch_id' => $this->branch->id,
            'work_shift_id' => $retiredShift->id,
            'effective_from' => '2026-10-01',
        ]);

        $this->actingAs($this->manager)
            ->from(route('admin.employee-shift-plans.index'))
            ->post(route('admin.employee-shift-plans.generate'), [
                'branch_id' => $this->branch->id,
                'month' => '2026-10',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'Đã tạo 31 ca')
                && str_contains($message, 'Còn 31 ca không tạo được')
                && str_contains($message, 'Ca này đã ngừng sử dụng'));

        $this->assertSame(31, ShiftAssignment::query()->where('employee_id', $this->employee->id)->count());
        $this->assertSame(0, ShiftAssignment::query()->where('employee_id', $other->id)->count());
    }

    public function test_an_unparseable_period_falls_back_instead_of_failing(): void
    {
        $this->actingAs($this->manager)
            ->get(route('admin.shift-schedule.index', ['week' => 'khong-phai-ngay']))
            ->assertOk();

        $this->actingAs($this->manager)
            ->get(route('admin.employee-shift-plans.index', ['month' => 'khong-phai-thang']))
            ->assertOk();
    }

    /**
     * Ca đang thuộc chi nhánh nào thì ở nguyên đó.
     *
     * Quản lý hai cơ sở, đang đứng ở cơ sở B mà sửa giờ một ca của cơ sở A,
     * từng làm ca đó lặng lẽ chuyển sang B và biến mất khỏi form phân công của A.
     */
    public function test_editing_a_shift_keeps_it_at_its_own_branch(): void
    {
        $otherBranch = Branch::factory()->create();
        $this->manager->branchAssignments()->create([
            'branch_id' => $otherBranch->id,
            'is_primary' => false,
            'starts_on' => '2000-01-01',
        ]);

        $this->actingAs($this->manager)
            ->withSession(['admin.current_branch_id' => $otherBranch->id])
            ->put(route('admin.work-shifts.update', $this->shift), [
                'name' => $this->shift->name,
                'starts_at' => '10:00',
                'ends_at' => '18:00',
                'shift_value' => 1,
                'grace_minutes' => 0,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.work-shifts.index'));

        $this->assertSame($this->branch->id, $this->shift->fresh()->branch_id);
    }

    private function lockPayrollForOctober(): void
    {
        Payroll::factory()->create([
            'employee_id' => $this->employee->id,
            'paying_branch_id' => $this->branch->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'status' => PayrollStatus::Finalized,
        ]);
    }
}
