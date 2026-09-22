<?php

namespace App\Services\Ai\Actions;

use App\Actions\Cash\RecordCashTransactionAction;
use App\Actions\Cash\VoidCashTransactionAction;
use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\PaymentMethod;
use App\Http\Requests\Admin\CashTransactionRequest;
use App\Http\Requests\Admin\VoidCashTransactionRequest;
use App\Models\CashTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Thao tác trên sổ thu chi.
 *
 * Cả ba đều mượn thẳng FormRequest của màn hình thu chi, nên phiếu của trợ lý
 * chịu đúng những ràng buộc mà kế toán đã quen: không ghi ngày tương lai,
 * không ghi lùi quá hạn chốt sổ, hủy phiếu phải nêu lý do đủ dài.
 */
final class CashActions
{
    /** @return array<int, ActionDefinition> */
    public static function all(): array
    {
        return [
            self::create(),
            self::update(),
            self::void(),
        ];
    }

    private static function create(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'create_cash_entry',
            label: 'Tạo khoản thu/chi',
            operation: 'create',
            resource: 'cash_transaction',
            destructive: false,
            fields: fn (?int $branchId): array => self::fields(),
            validator: Validation::formRequest(CashTransactionRequest::class),
            handler: fn (array $payload, User $actor, ?Model $subject): Model => app(RecordCashTransactionAction::class)->handle($payload, $actor),
        );
    }

    private static function update(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'update_cash_entry',
            label: 'Sửa khoản thu/chi',
            operation: 'update',
            resource: 'cash_transaction',
            destructive: false,
            fields: fn (?int $branchId): array => array_merge(
                [Field::select('cash_transaction_id', 'Phiếu cần sửa', fn (?int $id): array => Catalogue::cashEntries($id))->required()],
                self::fields(),
            ),
            validator: Validation::formRequest(CashTransactionRequest::class, 'cash_transaction'),
            handler: fn (array $payload, User $actor, ?Model $subject): Model => app(RecordCashTransactionAction::class)
                ->handle($payload, $actor, $subject),
            subject: self::subject(),
        );
    }

    private static function void(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'void_cash_entry',
            label: 'Hủy khoản thu/chi',
            operation: 'delete',
            resource: 'cash_transaction',
            destructive: true,
            fields: fn (?int $branchId): array => [
                Field::select('cash_transaction_id', 'Phiếu cần hủy', fn (?int $id): array => Catalogue::cashEntries($id))->required(),
                Field::textarea('void_reason', 'Lý do hủy')->required()->help('Ít nhất 10 ký tự, nói rõ vì sao hủy.'),
            ],
            validator: Validation::formRequest(VoidCashTransactionRequest::class, 'cash_transaction'),
            handler: fn (array $payload, User $actor, ?Model $subject): Model => app(VoidCashTransactionAction::class)
                ->handle($subject, $actor, $payload['void_reason']),
            subject: self::subject(),
            branchScoped: false,
            hint: 'Giữ lại phiếu gốc và ghi bút toán đảo, không xóa khỏi sổ.',
        );
    }

    private static function subject(): \Closure
    {
        return Subject::inBranch(CashTransaction::class, 'cash_transaction_id', 'khoản thu chi');
    }

    /** @return array<int, Field> */
    private static function fields(): array
    {
        return [
            Field::select('type', 'Thu hay chi', CashTransactionType::options())
                ->default(CashTransactionType::Expense->value)->required(),
            Field::select('category', 'Hạng mục', CashTransactionCategory::manualOptions())->required(),
            Field::money('amount', 'Số tiền')->required(),
            Field::select('payment_method', 'Hình thức thanh toán', PaymentMethod::options()),
            Field::datetime('occurred_at', 'Thời điểm')->default(now()->format('Y-m-d\TH:i'))->required(),
            Field::text('reference', 'Số chứng từ'),
            Field::textarea('note', 'Ghi chú'),
        ];
    }
}
