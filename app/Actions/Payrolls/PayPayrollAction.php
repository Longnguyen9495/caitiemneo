<?php

namespace App\Actions\Payrolls;

use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\PayrollStatus;
use App\Models\CashTransaction;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pay a finalised payroll and post the matching expense in the cash book.
 *
 * Same idempotency contract as the invoice payment: row lock, early return on an
 * already paid payroll, and a unique idempotency key on the cash transaction.
 */
class PayPayrollAction
{
    /** @throws ValidationException */
    public function handle(Payroll $payroll, User $actor, PaymentMethod $paymentMethod): Payroll
    {
        return DB::transaction(function () use ($payroll, $actor, $paymentMethod): Payroll {
            $locked = Payroll::query()->lockForUpdate()->with('employee')->findOrFail($payroll->getKey());

            if ($locked->status === PayrollStatus::Paid) {
                return $locked;
            }

            if ($locked->status !== PayrollStatus::Finalized) {
                throw ValidationException::withMessages([
                    'status' => 'Bảng lương phải được chốt trước khi chi trả.',
                ]);
            }

            $paidAt = now();

            $locked->forceFill([
                'status' => PayrollStatus::Paid,
                'paid_at' => $paidAt,
            ])->save();

            CashTransaction::query()->create([
                'branch_id' => $this->payingBranchId($locked),
                'payroll_id' => $locked->getKey(),
                'created_by' => $actor->getKey(),
                'type' => CashTransactionType::Expense,
                'category' => CashTransactionCategory::Payroll,
                'amount' => $locked->final_total,
                'payment_method' => $paymentMethod,
                'reference' => 'LUONG-'.$locked->getKey(),
                'idempotency_key' => self::paymentKey($locked),
                'note' => $this->describe($locked),
                'occurred_at' => $paidAt,
            ]);

            return $locked;
        });
    }

    /**
     * Which branch's cash box pays the salary.
     *
     * A single expense is written against the paying branch rather than one per
     * allocation, so the cash book shows the money leaving exactly once; the
     * per-branch cost split stays visible in the payroll allocations.
     */
    private function payingBranchId(Payroll $payroll): int
    {
        return (int) ($payroll->paying_branch_id
            ?? $payroll->allocations()->orderByDesc('subtotal')->value('branch_id')
            ?? $payroll->employee?->primaryBranchId($payroll->period_end->toDateString()));
    }

    public static function paymentKey(Payroll $payroll): string
    {
        return 'payroll-payment:'.$payroll->getKey();
    }

    private function describe(Payroll $payroll): string
    {
        return sprintf(
            'Chi lương %s kỳ %s - %s',
            $payroll->employee?->name ?? 'nhân viên',
            $payroll->period_start->format('d/m/Y'),
            $payroll->period_end->format('d/m/Y'),
        );
    }
}
