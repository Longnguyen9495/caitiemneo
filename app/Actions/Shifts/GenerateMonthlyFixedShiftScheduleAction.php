<?php

namespace App\Actions\Shifts;

use App\Actions\Attendance\ScheduleShiftAction;
use App\Models\Branch;
use App\Models\EmployeeFixedShift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GenerateMonthlyFixedShiftScheduleAction
{
    public function __construct(private ScheduleShiftAction $schedule) {}

    /** @return array{created: int, skipped: int} */
    public function handle(User $actor, Branch $branch, string $month): array
    {
        $start = CarbonImmutable::parse($month)->startOfMonth();
        $end = $start->endOfMonth();

        if ((! $actor->isOwner() && ! $actor->isManager()) || ! $actor->canAccessBranch($branch, $start)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Bạn không có quyền tạo lịch ca cố định tại chi nhánh này.',
            ]);
        }

        $created = 0;
        $skipped = 0;

        $fixedShifts = EmployeeFixedShift::query()
            ->with(['employee', 'workShift'])
            ->where('branch_id', $branch->getKey())
            ->whereDate('effective_from', '<=', $end)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start))
            ->orderBy('employee_id')
            ->get();

        foreach ($fixedShifts as $fixedShift) {
            foreach ($this->dates($start, $end) as $date) {
                if (! $fixedShift->employee->is_active
                    || ! $fixedShift->employee->canAccessBranch($branch, $date)
                    || ! $this->isEffective($fixedShift, $date)
                    || ShiftAssignment::query()->where('employee_id', $fixedShift->employee_id)->whereDate('work_date', $date)->exists()) {
                    $skipped++;

                    continue;
                }

                $this->schedule->handle($branch, $fixedShift->workShift, $fixedShift->employee, $date, $actor, 'Tạo từ ca cố định theo tháng.');
                $created++;
            }
        }

        return compact('created', 'skipped');
    }

    /** @return Collection<int, string> */
    private function dates(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return collect($start->toPeriod($end))->map(fn (CarbonImmutable $date): string => $date->toDateString());
    }

    private function isEffective(EmployeeFixedShift $fixedShift, string $date): bool
    {
        return $fixedShift->effective_from->toDateString() <= $date
            && ($fixedShift->effective_to === null || $fixedShift->effective_to->toDateString() >= $date);
    }
}
