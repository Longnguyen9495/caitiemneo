<?php

namespace App\Services\Payroll;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\PayrollPolicy;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Decides whether an employee earned the attendance bonus for a period.
 *
 * What counts as an absence is policy data, not a constant: a shop may or may
 * not treat approved leave and lateness as breaking the streak.
 */
class AttendanceEvaluator
{
    /**
     * @return array{
     *     eligible: bool,
     *     allowed_absence_days: int,
     *     actual_absence_days: int,
     *     unexcused_absence_days: int,
     *     bonus_amount: string,
     *     reason: string,
     *     source_policy_id: int|null
     * }
     */
    public function evaluate(
        int $employeeId,
        ?PayrollPolicy $policy,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
    ): array {
        if ($policy === null) {
            return $this->result(false, 0, 0, 0, 0, 'Chưa có chính sách lương áp dụng cho kỳ này.', null);
        }

        $records = AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$periodStart->copy()->startOfDay(), $periodEnd->copy()->endOfDay()])
            ->get(['status', 'work_date']);

        $unexcused = $this->countDistinctDays($records, fn (AttendanceStatus $status): bool => $status === AttendanceStatus::Absent);
        $counted = $this->countDistinctDays($records, fn (AttendanceStatus $status): bool => $this->countsAsAbsence($status, $policy));

        $allowed = (int) $policy->allowed_absence_days;
        $eligible = $counted <= $allowed;
        $bonusMinor = $eligible ? Money::toMinor($policy->attendance_bonus_amount) : 0;

        $reason = $eligible
            ? sprintf('Nghỉ %d/%d ngày cho phép.', $counted, $allowed)
            : sprintf('Nghỉ %d ngày, vượt mức cho phép %d ngày.', $counted, $allowed);

        return $this->result($eligible, $allowed, $counted, $unexcused, $bonusMinor, $reason, $policy->getKey());
    }

    /**
     * @param  Collection<int, AttendanceRecord>  $records
     * @param  callable(AttendanceStatus): bool  $matches
     */
    private function countDistinctDays($records, callable $matches): int
    {
        return $records
            ->filter(fn (AttendanceRecord $record): bool => $matches($record->status))
            ->map(fn (AttendanceRecord $record): string => $record->work_date->toDateString())
            ->unique()
            ->count();
    }

    /** @return array<string, mixed> */
    private function result(bool $eligible, int $allowed, int $actual, int $unexcused, int $bonusMinor, string $reason, ?int $policyId): array
    {
        return [
            'eligible' => $eligible,
            'allowed_absence_days' => $allowed,
            'actual_absence_days' => $actual,
            'unexcused_absence_days' => $unexcused,
            'bonus_amount' => Money::toDecimal($bonusMinor),
            'reason' => $reason,
            'source_policy_id' => $policyId,
        ];
    }

    private function countsAsAbsence(AttendanceStatus $status, PayrollPolicy $policy): bool
    {
        return match ($status) {
            AttendanceStatus::Absent => true,
            AttendanceStatus::Leave => (bool) $policy->excused_leave_counts_as_absence,
            AttendanceStatus::Late => (bool) $policy->late_counts_as_absence,
            AttendanceStatus::Present => false,
        };
    }
}
