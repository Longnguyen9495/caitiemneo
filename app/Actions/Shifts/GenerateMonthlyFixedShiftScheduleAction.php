<?php

namespace App\Actions\Shifts;

use App\Actions\Attendance\ScheduleShiftAction;
use App\Actions\Shifts\Concerns\AssertsBranchLeadership;
use App\Models\Branch;
use App\Models\EmployeeFixedShift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerateMonthlyFixedShiftScheduleAction
{
    use AssertsBranchLeadership;

    public function __construct(private ScheduleShiftAction $schedule) {}

    /**
     * Roster a whole month from the fixed-shift plan.
     *
     * One unusable plan — a template that was switched off, a day that clashes
     * with an overnight shift — must not decide the fate of the other thirty.
     * Such a day is counted and its reason reported, and the run carries on;
     * the whole month is written in one transaction so a genuine database
     * failure leaves nothing half-built.
     *
     * @return array{created: int, skipped: int, failed: int, reasons: array<int, string>}
     */
    public function handle(User $actor, Branch $branch, string $month): array
    {
        $start = CarbonImmutable::parse($month)->startOfMonth();
        $end = $start->endOfMonth();

        $this->assertLeadsBranchOn(
            $actor,
            $branch->getKey(),
            $start,
            'branch_id',
            'Bạn không có quyền tạo lịch ca cố định tại chi nhánh này.',
        );

        $dates = $this->dates($start, $end);

        $fixedShifts = EmployeeFixedShift::query()
            ->with(['employee', 'workShift'])
            ->where('branch_id', $branch->getKey())
            ->effectiveBetween($start->toDateString(), $end->toDateString())
            ->orderBy('employee_id')
            ->get();

        return DB::transaction(function () use ($actor, $branch, $dates, $fixedShifts): array {
            $created = 0;
            $skipped = 0;
            $failed = 0;
            $reasons = [];

            foreach ($fixedShifts as $fixedShift) {
                foreach ($dates as $date) {
                    if (! $this->isRosterable($fixedShift, $branch, $date)) {
                        $skipped++;

                        continue;
                    }

                    try {
                        $this->schedule->handle($branch, $fixedShift->workShift, $fixedShift->employee, $date, $actor, 'Tạo từ ca cố định theo tháng.');
                        $created++;
                    } catch (ValidationException $exception) {
                        $failed++;

                        foreach ($exception->errors() as $messages) {
                            foreach ($messages as $message) {
                                $reasons[$message] = true;
                            }
                        }
                    }
                }
            }

            return [
                'created' => $created,
                'skipped' => $skipped,
                'failed' => $failed,
                'reasons' => array_keys($reasons),
            ];
        });
    }

    /** @return Collection<int, string> */
    private function dates(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return collect($start->toPeriod($end))->map(fn (CarbonImmutable $date): string => $date->toDateString());
    }

    /**
     * A day the plan genuinely asks for and nothing already covers.
     *
     * These are the ordinary reasons to pass a day over, so they are counted
     * as skipped rather than reported back as something that went wrong.
     */
    private function isRosterable(EmployeeFixedShift $fixedShift, Branch $branch, string $date): bool
    {
        return $fixedShift->employee->is_active
            && $fixedShift->employee->canAccessBranch($branch, $date)
            && $fixedShift->coversDate($date)
            && ! ShiftAssignment::query()
                ->forEmployee($fixedShift->employee_id)
                ->forDate($date)
                ->exists();
    }
}
