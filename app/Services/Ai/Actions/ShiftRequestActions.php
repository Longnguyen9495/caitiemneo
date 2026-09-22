<?php

namespace App\Services\Ai\Actions;

use App\Actions\Shifts\AssignShiftReplacementAction;
use App\Actions\Shifts\ManageShiftRequestAction;
use App\Models\ShiftRequest;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Đơn xin nghỉ và đổi ca.
 *
 * Ba thao tác quyết định số phận một lá đơn, nên cái nào cũng có ô ghi chú:
 * người gửi đơn cần biết vì sao được duyệt hay bị từ chối, và đó cũng là thứ
 * còn lại trong lịch sử khi mọi người đã quên.
 */
final class ShiftRequestActions
{
    /** @return array<int, ActionDefinition> */
    public static function all(): array
    {
        return [
            self::decide(),
            self::cancel(),
            self::assignReplacement(),
        ];
    }

    private static function decide(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'decide_shift_request',
            label: 'Duyệt hoặc từ chối đơn ca',
            operation: 'update',
            resource: 'shift_request',
            destructive: false,
            fields: fn (?int $branchId): array => [
                self::picker('Đơn cần xử lý'),
                Field::select('approved', 'Kết luận', ['1' => 'Duyệt đơn', '0' => 'Từ chối'])->required(),
                Field::textarea('note', 'Ghi chú cho người gửi'),
            ],
            validator: Validation::inline([
                'shift_request_id' => ['required', 'integer'],
                'approved' => ['required', 'boolean'],
                'note' => ['nullable', 'string', 'max:255'],
            ], self::ATTRIBUTES),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                Gate::forUser($actor)->authorize('decide', $subject);
                app(ManageShiftRequestAction::class)->decide(
                    $actor,
                    $subject,
                    (bool) $payload['approved'],
                    (string) ($payload['note'] ?? ''),
                );

                return $subject->refresh();
            },
            subject: self::subject(),
            branchScoped: false,
        );
    }

    private static function cancel(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'cancel_shift_request',
            label: 'Hủy đơn ca',
            operation: 'delete',
            resource: 'shift_request',
            destructive: true,
            fields: fn (?int $branchId): array => [
                self::picker('Đơn cần hủy'),
                Field::textarea('note', 'Lý do hủy'),
            ],
            validator: Validation::inline([
                'shift_request_id' => ['required', 'integer'],
                'note' => ['nullable', 'string', 'max:255'],
            ], self::ATTRIBUTES),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                Gate::forUser($actor)->authorize('cancel', $subject);
                app(ManageShiftRequestAction::class)->cancel($actor, $subject, (string) ($payload['note'] ?? ''));

                return $subject->refresh();
            },
            subject: self::subject(),
            branchScoped: false,
            hint: 'Đơn vẫn nằm trong lịch sử, chỉ chuyển sang trạng thái đã hủy.',
        );
    }

    private static function assignReplacement(): ActionDefinition
    {
        return new ActionDefinition(
            key: 'assign_shift_replacement',
            label: 'Phân người thay ca',
            operation: 'update',
            resource: 'shift_request',
            destructive: false,
            fields: fn (?int $branchId): array => [
                self::picker('Đơn nghỉ cần người thay'),
                Field::select('replacement_employee_id', 'Người thay ca', fn (?int $id): array => Catalogue::employees($id))->required(),
            ],
            validator: Validation::inline([
                'shift_request_id' => ['required', 'integer'],
                'replacement_employee_id' => ['required', 'integer', Rule::exists(User::class, 'id')],
            ], self::ATTRIBUTES),
            handler: function (array $payload, User $actor, ?Model $subject): Model {
                Gate::forUser($actor)->authorize('assignReplacement', $subject);
                $replacement = User::query()->findOrFail($payload['replacement_employee_id']);
                app(AssignShiftReplacementAction::class)->handle($actor, $subject, $replacement);

                return $subject->refresh();
            },
            subject: self::subject(),
            branchScoped: false,
        );
    }

    private static function picker(string $label): Field
    {
        return Field::select('shift_request_id', $label, fn (?int $id): array => Catalogue::shiftRequests($id))->required();
    }

    private static function subject(): Closure
    {
        return Subject::inBranch(ShiftRequest::class, 'shift_request_id', 'đơn ca');
    }

    /** @var array<string, string> */
    private const ATTRIBUTES = [
        'shift_request_id' => 'đơn ca',
        'approved' => 'kết luận',
        'note' => 'ghi chú',
        'replacement_employee_id' => 'người thay ca',
    ];
}
