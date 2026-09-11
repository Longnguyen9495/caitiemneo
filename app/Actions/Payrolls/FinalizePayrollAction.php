<?php

namespace App\Actions\Payrolls;

use App\Enums\PayrollStatus;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lock a draft payroll. The figures are recalculated one last time and the row
 * becomes read-only from that point on.
 */
class FinalizePayrollAction
{
    public function __construct(private CalculatePayrollAction $calculate) {}

    /** @throws ValidationException */
    public function handle(Payroll $payroll, User $actor): Payroll
    {
        return DB::transaction(function () use ($payroll, $actor): Payroll {
            $locked = Payroll::query()->lockForUpdate()->findOrFail($payroll->getKey());

            if ($locked->status === PayrollStatus::Finalized) {
                return $locked;
            }

            if ($locked->status !== PayrollStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ bảng lương nháp mới có thể chốt.',
                ]);
            }

            $this->calculate->refresh($locked);

            $locked->forceFill([
                'status' => PayrollStatus::Finalized,
                'finalized_at' => now(),
                'finalized_by' => $actor->getKey(),
            ])->save();

            return $locked;
        });
    }
}
