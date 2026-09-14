<?php

namespace App\Actions\Shifts;

use App\Enums\AuditAction;
use App\Models\Branch;
use App\Models\EmployeeFixedShift;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Audit\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfigureEmployeeFixedShiftAction
{
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
                throw ValidationException::withMessages(['effective_from' => 'Nhân viên đã có ca cố định chồng lấn trong khoảng ngày này.']);
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

    private function assertScope(User $actor, User $employee, Branch $branch, WorkShift $workShift, string $onDate): void
    {
        if ((! $actor->isOwner() && ! $actor->isManager()) || ! $actor->canAccessBranch($branch, $onDate)) {
            throw ValidationException::withMessages(['branch_id' => 'Bạn không có quyền cấu hình ca tại chi nhánh này.']);
        }

        if (! $employee->is_active || ! $employee->canAccessBranch($branch, $onDate)) {
            throw ValidationException::withMessages(['employee_id' => 'Nhân viên không được phân công tại chi nhánh vào ngày hiệu lực.']);
        }

        if (! $workShift->is_active || ($workShift->branch_id !== null && (int) $workShift->branch_id !== (int) $branch->getKey())) {
            throw ValidationException::withMessages(['work_shift_id' => 'Ca làm không khả dụng tại chi nhánh này.']);
        }
    }
}
