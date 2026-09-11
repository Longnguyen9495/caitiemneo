<?php

namespace App\Actions\Payrolls;

use App\Enums\PayrollStatus;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Void a payroll that has not been paid yet. Paid payrolls stay untouched so the
 * cash book and the salary history never disagree.
 */
class CancelPayrollAction
{
    /** @throws ValidationException */
    public function handle(Payroll $payroll, User $actor, ?string $reason = null): Payroll
    {
        return DB::transaction(function () use ($payroll, $actor, $reason): Payroll {
            $locked = Payroll::query()->lockForUpdate()->findOrFail($payroll->getKey());

            if ($locked->status === PayrollStatus::Cancelled) {
                return $locked;
            }

            if ($locked->status === PayrollStatus::Paid) {
                throw ValidationException::withMessages([
                    'status' => 'Bảng lương đã chi trả không thể hủy.',
                ]);
            }

            $locked->forceFill([
                'status' => PayrollStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->getKey(),
                'note' => trim(implode(' ', array_filter([$locked->note, $reason]))) ?: null,
            ])->save();

            return $locked;
        });
    }
}
