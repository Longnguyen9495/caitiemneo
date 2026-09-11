<?php

namespace App\Actions\Cash;

use App\Enums\AuditAction;
use App\Models\CashTransaction;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Soft-cancel a manual cash book entry.
 *
 * Financial rows are never deleted: the entry stays in place with a void stamp so
 * the audit trail survives, and balances simply skip voided rows.
 */
class VoidCashTransactionAction
{
    public function __construct(private AuditRecorder $auditor) {}

    /** @throws ValidationException */
    public function handle(CashTransaction $transaction, User $actor, ?string $reason = null): CashTransaction
    {
        return DB::transaction(function () use ($transaction, $actor, $reason): CashTransaction {
            $locked = CashTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());

            if ($locked->isSystemGenerated()) {
                throw ValidationException::withMessages([
                    'void_reason' => 'Giao dịch sinh tự động phải được hủy từ hóa đơn hoặc bảng lương tương ứng.',
                ]);
            }

            if ($locked->isVoided()) {
                return $locked;
            }

            $before = $locked->auditSnapshot();

            $locked->forceFill([
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ])->save();

            $this->auditor->record(
                $locked,
                $actor,
                AuditAction::Voided,
                $before,
                $locked->auditSnapshot(),
                $reason,
            );

            return $locked;
        });
    }
}
