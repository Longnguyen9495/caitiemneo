<?php

namespace App\Actions\Payrolls;

use App\Enums\PayrollAdjustmentCategory as Category;
use App\Enums\PayrollAdjustmentDirection as Direction;
use App\Enums\PayrollStatus;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create a draft payroll, or edit the hand-entered part of an existing one.
 *
 * The quick "phụ cấp / khấu trừ" fields of the form are materialised into real
 * {@see PayrollAdjustment} rows rather than living as two opaque totals, so the
 * payslip can always explain where every dong came from.
 */
class SavePayrollAction
{
    private const QUICK_ALLOWANCE_SOURCE = 'quick_allowance';

    private const QUICK_DEDUCTION_SOURCE = 'quick_deduction';

    public function __construct(private CalculatePayrollAction $calculate) {}

    /**
     * @param  array{employee_id: int|string, period_start: string, period_end: string, paying_branch_id?: int|string|null, adjustment?: mixed, deduction?: mixed, note?: string|null}  $data
     *
     * @throws ValidationException when the employee already has a payroll covering the period
     */
    public function create(array $data, User $actor): Payroll
    {
        return DB::transaction(function () use ($data, $actor): Payroll {
            $employee = User::query()->lockForUpdate()->findOrFail($data['employee_id']);
            $periodStart = Carbon::parse($data['period_start'])->startOfDay();
            $periodEnd = Carbon::parse($data['period_end'])->endOfDay();

            $this->guardAgainstOverlappingPeriod($employee, $periodStart, $periodEnd);

            $payingBranchId = ($data['paying_branch_id'] ?? null) ?: $employee->primaryBranchId($periodStart->toDateString());

            $payroll = Payroll::query()->create([
                'employee_id' => $employee->getKey(),
                'paying_branch_id' => $payingBranchId,
                'created_by' => $actor->getKey(),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'status' => PayrollStatus::Draft,
                'note' => $data['note'] ?? null,
            ]);

            $this->syncQuickAdjustments($payroll, $data, $actor);

            return $this->calculate->refresh($payroll);
        });
    }

    /**
     * @param  array{adjustment?: mixed, deduction?: mixed, note?: string|null}  $data
     *
     * @throws ValidationException when the payroll is no longer a draft
     */
    public function update(Payroll $payroll, array $data, ?User $actor = null): Payroll
    {
        return DB::transaction(function () use ($payroll, $data, $actor): Payroll {
            $locked = Payroll::query()->lockForUpdate()->findOrFail($payroll->getKey());

            if (! $locked->isEditable()) {
                throw ValidationException::withMessages([
                    'status' => 'Bảng lương đã chốt hoặc đã trả không thể chỉnh sửa.',
                ]);
            }

            $locked->forceFill(['note' => $data['note'] ?? null])->save();

            $this->syncQuickAdjustments($locked, $data, $actor);

            return $this->calculate->refresh($locked);
        });
    }

    /**
     * Keep the two quick-entry fields in step with their adjustment rows.
     *
     * They are matched by `source_type`, so editing the form updates the same
     * row instead of stacking a new one on every save.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncQuickAdjustments(Payroll $payroll, array $data, ?User $actor): void
    {
        $this->syncQuickRow(
            $payroll,
            self::QUICK_ALLOWANCE_SOURCE,
            Category::Allowance,
            Direction::Earning,
            'Phụ cấp theo kỳ',
            $data['adjustment'] ?? null,
            $actor,
        );

        $this->syncQuickRow(
            $payroll,
            self::QUICK_DEDUCTION_SOURCE,
            Category::Other,
            Direction::Deduction,
            'Khấu trừ theo kỳ',
            $data['deduction'] ?? null,
            $actor,
        );
    }

    private function syncQuickRow(
        Payroll $payroll,
        string $sourceType,
        Category $category,
        Direction $direction,
        string $description,
        mixed $amount,
        ?User $actor,
    ): void {
        if ($amount === null) {
            return;
        }

        $amountMinor = abs(Money::toMinor($amount));

        $existing = PayrollAdjustment::query()
            ->where('payroll_id', $payroll->getKey())
            ->where('source_type', $sourceType)
            ->first();

        if ($amountMinor === 0) {
            $existing?->delete();

            return;
        }

        $attributes = [
            'payroll_id' => $payroll->getKey(),
            'branch_id' => $payroll->paying_branch_id,
            'category' => $category,
            'direction' => $direction,
            'amount' => Money::toDecimal($amountMinor),
            'description' => $description,
            'source_type' => $sourceType,
            'is_automatic' => false,
            'created_by' => $actor?->getKey(),
        ];

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return;
        }

        PayrollAdjustment::query()->create($attributes);
    }

    /** @throws ValidationException */
    private function guardAgainstOverlappingPeriod(User $employee, Carbon $periodStart, Carbon $periodEnd): void
    {
        $overlaps = Payroll::query()
            ->where('employee_id', $employee->getKey())
            ->where('status', '!=', PayrollStatus::Cancelled)
            ->whereDate('period_start', '<=', $periodEnd->toDateString())
            ->whereDate('period_end', '>=', $periodStart->toDateString())
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'period_start' => 'Nhân viên đã có bảng lương trùng với kỳ này.',
            ]);
        }
    }
}
