<?php

namespace App\Actions\Shifts;

use App\Actions\Shifts\Concerns\AssertsBranchLeadership;
use App\Enums\AuditAction;
use App\Models\Branch;
use App\Models\EmployeeFixedShift;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Audit\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The standing arrangement that says which shift somebody works by default.
 *
 * One employee holds at most one of these at a time, because the monthly
 * generator writes at most one shift per person per day: a second overlapping
 * plan would sit there looking effective and silently never produce anything.
 * That is why {@see end()} exists — without a way to close the current plan,
 * the rule against overlap would make the first one permanent.
 */
class ConfigureEmployeeFixedShiftAction
{
    use AssertsBranchLeadership;

    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        User $actor,
        User $employee,
        Branch $branch,
        WorkShift $workShift,
        string $effectiveFrom,
        ?string $effectiveTo = null,
    ): EmployeeFixedShift {
        $from = CarbonImmutable::parse($effectiveFrom)->toDateString();
        $to = $effectiveTo === null ? null : CarbonImmutable::parse($effectiveTo)->toDateString();

        if ($to !== null && $to < $from) {
            throw ValidationException::withMessages(['effective_to' => 'Ngày kết thúc phải từ ngày hiệu lực trở đi.']);
        }

        return DB::transaction(function () use ($actor, $employee, $branch, $workShift, $from, $to): EmployeeFixedShift {
            $this->assertScope($actor, $employee, $branch, $workShift, $from);

            $overlaps = EmployeeFixedShift::query()
                ->where('employee_id', $employee->getKey())
                ->lockForUpdate()
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
                ->when($to !== null, fn ($query) => $query->whereDate('effective_from', '<=', $to))
                ->exists();

            if ($overlaps) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Nhân viên đã có ca cố định chồng lấn trong khoảng ngày này. Hãy kết thúc ca cố định đang chạy trước.',
                ]);
            }

            $fixedShift = EmployeeFixedShift::query()->create([
                'employee_id' => $employee->getKey(),
                'branch_id' => $branch->getKey(),
                'work_shift_id' => $workShift->getKey(),
                'effective_from' => $from,
                'effective_to' => $to,
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->record($fixedShift, $actor, AuditAction::Created, null, $fixedShift->getAttributes(), null, $branch->getKey());

            return $fixedShift;
        });
    }

    /**
     * Close a standing arrangement on a given date.
     *
     * Closing rather than deleting, because the plan explains why last month's
     * roster looks the way it does. The end date may be in the past — a
     * manager writing down an arrangement that already lapsed is the ordinary
     * case — but never before the plan began, which would leave a window that
     * covers no days at all.
     */
    public function end(User $actor, EmployeeFixedShift $fixedShift, string $effectiveTo): EmployeeFixedShift
    {
        $to = CarbonImmutable::parse($effectiveTo)->toDateString();

        return DB::transaction(function () use ($actor, $fixedShift, $to): EmployeeFixedShift {
            $fixedShift = EmployeeFixedShift::query()->lockForUpdate()->findOrFail($fixedShift->getKey());
            $this->assertMayManage($actor, $fixedShift);

            if ($to < $fixedShift->effective_from->toDateString()) {
                throw ValidationException::withMessages([
                    'effective_to' => 'Ngày kết thúc phải từ ngày hiệu lực trở đi.',
                ]);
            }

            $before = $fixedShift->getAttributes();
            $fixedShift->update(['effective_to' => $to]);

            $this->audit->record($fixedShift, $actor, AuditAction::Updated, $before, $fixedShift->getAttributes(), 'Kết thúc ca cố định.', $fixedShift->branch_id);

            return $fixedShift;
        });
    }

    /**
     * Drop a plan that never took effect.
     *
     * Mirrors how branch postings work: something that has already governed a
     * real day is closed with a date and kept, and only an arrangement whose
     * start is still ahead can be removed outright — nothing has happened
     * under it yet, so there is no history to lose.
     */
    public function remove(User $actor, EmployeeFixedShift $fixedShift): void
    {
        DB::transaction(function () use ($actor, $fixedShift): void {
            $fixedShift = EmployeeFixedShift::query()->lockForUpdate()->findOrFail($fixedShift->getKey());
            $this->assertMayManage($actor, $fixedShift);

            if ($fixedShift->hasTakenEffect()) {
                throw ValidationException::withMessages([
                    'effective_to' => 'Ca cố định đã có hiệu lực nên chỉ có thể kết thúc, không thể xóa.',
                ]);
            }

            $before = $fixedShift->getAttributes();
            $branchId = $fixedShift->branch_id;
            $fixedShift->delete();

            $this->audit->record($fixedShift, $actor, AuditAction::Deleted, $before, null, 'Xóa ca cố định chưa hiệu lực.', $branchId);
        });
    }

    private function assertMayManage(User $actor, EmployeeFixedShift $fixedShift): void
    {
        $this->assertLeadsBranchOn(
            $actor,
            $fixedShift->branch_id,
            $fixedShift->effective_from,
            'branch_id',
            'Bạn không có quyền sửa ca cố định tại chi nhánh này.',
        );
    }

    private function assertScope(User $actor, User $employee, Branch $branch, WorkShift $workShift, string $onDate): void
    {
        $this->assertLeadsBranchOn(
            $actor,
            $branch->getKey(),
            $onDate,
            'branch_id',
            'Bạn không có quyền cấu hình ca tại chi nhánh này.',
        );

        if (! $employee->is_active || ! $employee->canAccessBranch($branch, $onDate)) {
            throw ValidationException::withMessages(['employee_id' => 'Nhân viên không được phân công tại chi nhánh vào ngày hiệu lực.']);
        }

        if (! $workShift->is_active || ($workShift->branch_id !== null && (int) $workShift->branch_id !== (int) $branch->getKey())) {
            throw ValidationException::withMessages(['work_shift_id' => 'Ca làm không khả dụng tại chi nhánh này.']);
        }
    }
}
