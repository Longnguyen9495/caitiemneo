<?php

namespace App\Services\Ai;

use App\Enums\AiActionStatus;
use App\Enums\AuditAction;
use App\Models\AiActionProposal;
use App\Models\User;
use App\Services\Ai\Actions\ActionDefinition;
use App\Services\Ai\Actions\ActionRegistry;
use App\Services\Audit\AuditRecorder;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Chạy một đề xuất sau khi người duyệt đã bấm nút.
 *
 * Lớp này không biết gì về từng loại thao tác: luật, quyền và chỗ ghi dữ liệu
 * đều nằm trong bản khai ở App\Services\Ai\Actions. Việc của nó là những thứ
 * chung cho mọi thao tác — khóa bản ghi, chặn người lạ, nhận dữ liệu người
 * duyệt sửa, và để lại dấu vết.
 */
class AiActionExecutor
{
    public function __construct(
        private BranchContext $branches,
        private ActionRegistry $registry,
        private AiActionFormBuilder $forms,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>|null  $submitted  Dữ liệu người duyệt gửi từ
     *                                                phiếu xác nhận; null nghĩa
     *                                                là chạy đúng đề xuất gốc.
     */
    public function execute(AiActionProposal $proposal, User $actor, ?array $submitted = null): AiActionProposal
    {
        try {
            return DB::transaction(function () use ($proposal, $actor, $submitted): AiActionProposal {
                $locked = AiActionProposal::query()->lockForUpdate()->findOrFail($proposal->id);

                if ($locked->status !== AiActionStatus::Pending) {
                    return $locked->load('result');
                }

                $action = $this->action($locked);
                $this->guardOwnership($locked, $actor);

                if ($submitted !== null) {
                    $this->applySubmitted($locked, $submitted, $actor);
                }

                if ($action->branchScoped) {
                    $this->guardBranch($locked);
                }

                // Bản ghi đích tra một lần rồi dùng cho cả kiểm luật lẫn lúc
                // chạy: không có khe hở giữa thứ đã kiểm và thứ bị sửa.
                $subject = $action->subject($locked->payload ?? []);
                $payload = $action->validate($locked->payload ?? [], $actor, $locked->branch_id, $subject);

                // Chụp lại bản ghi trước khi đụng vào. Với thao tác xóa cứng
                // thì đây là bản sao duy nhất còn lại của dòng vừa mất, nên
                // phải lấy trước khi gọi handler chứ không phải sau.
                $before = $subject?->attributesToArray();

                $locked->forceFill(['status' => AiActionStatus::Executing])->save();
                $result = $action->handle($payload, $actor, $subject);

                $locked->forceFill([
                    'status' => AiActionStatus::Executed,
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                    'result_type' => $result->getMorphClass(),
                    'result_id' => $result->getKey(),
                    'failure_message' => null,
                ])->save();

                $this->recordApproval($locked, $action, $actor, $payload, $result, $before);

                return $locked->load('result');
            });
        } catch (ValidationException|HttpException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            DB::transaction(function () use ($proposal, $exception): void {
                $locked = AiActionProposal::query()->lockForUpdate()->findOrFail($proposal->id);

                if ($locked->status === AiActionStatus::Pending || $locked->status === AiActionStatus::Executing) {
                    $locked->forceFill([
                        'status' => AiActionStatus::Failed,
                        'failure_message' => $this->safeFailureMessage($exception),
                    ])->save();
                }
            });

            throw $exception;
        }
    }

    public function reject(AiActionProposal $proposal, User $actor): AiActionProposal
    {
        return DB::transaction(function () use ($proposal, $actor): AiActionProposal {
            $locked = AiActionProposal::query()->lockForUpdate()->findOrFail($proposal->id);

            if ($locked->status !== AiActionStatus::Pending) {
                return $locked;
            }

            $this->guardOwnership($locked, $actor);
            $locked->forceFill([
                'status' => AiActionStatus::Rejected,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ])->save();

            return $locked;
        });
    }

    private function action(AiActionProposal $proposal): ActionDefinition
    {
        $action = $this->registry->find($proposal->type);

        if ($action === null) {
            throw ValidationException::withMessages([
                'action' => 'Loại thao tác AI này không được hệ thống hỗ trợ.',
            ]);
        }

        return $action;
    }

    private function guardOwnership(AiActionProposal $proposal, User $actor): void
    {
        $belongsToActor = AiActionProposal::query()
            ->whereKey($proposal->getKey())
            ->whereHas('message.conversation', fn ($query) => $query->where('user_id', $actor->id))
            ->exists();

        if (! $belongsToActor) {
            abort(404);
        }

        Gate::forUser($actor)->authorize('use-ai-assistant');
    }

    private function guardBranch(AiActionProposal $proposal): void
    {
        $branchId = $proposal->branch_id;

        if ($branchId === null || ! in_array((int) $branchId, $this->branches->scopeIds(), true)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Chi nhánh của đề xuất không còn nằm trong phạm vi bạn được phép thao tác.',
            ]);
        }

        $payloadBranchId = $proposal->payload['branch_id'] ?? null;

        if ($payloadBranchId === null || (int) $payloadBranchId !== (int) $branchId) {
            throw ValidationException::withMessages([
                'branch_id' => 'Dữ liệu của đề xuất không khớp chi nhánh đã ghi nhận.',
            ]);
        }
    }

    /**
     * Ghi đè payload bằng đúng những gì người duyệt nhìn thấy trên phiếu.
     *
     * Chỉ các khóa có ô nhập thật mới được nhận: một khóa trợ lý bịa thêm, hay
     * một trường ai đó nhét vào request, đều rơi ra ở đây trước khi tới lớp
     * validate. Ô để trống được hiểu là "không có", không phải "giữ giá trị cũ",
     * vì đó là điều người duyệt thấy trên màn hình.
     *
     * @param  array<string, mixed>  $submitted
     */
    private function applySubmitted(AiActionProposal $proposal, array $submitted, User $actor): void
    {
        $before = $proposal->payload ?? [];
        $payload = [];

        foreach ($this->forms->editableKeys($proposal->type) as $key) {
            $payload[$key] = $this->normalise($submitted[$key] ?? null);
        }

        // Chi nhánh không phải ô người duyệt gõ, nhưng phải đi cùng payload;
        // phạm vi của nó do guardBranch và lớp validate kiểm lại.
        $branchId = $submitted['branch_id'] ?? $before['branch_id'] ?? $proposal->branch_id;
        $payload['branch_id'] = is_numeric($branchId) ? (int) $branchId : null;

        if ($payload == $before) {
            return;
        }

        $proposal->forceFill([
            'payload' => $payload,
            'branch_id' => $payload['branch_id'],
            'request_fingerprint' => AiActionProposal::fingerprintFor($proposal->type, $payload, $actor->getKey()),
        ])->save();

        $this->audit->record(
            subject: $proposal,
            actor: $actor,
            action: AuditAction::Updated,
            before: $before,
            after: $payload,
            reason: 'Người duyệt sửa lại phiếu của trợ lý AI trước khi thực hiện.',
            branchId: $payload['branch_id'],
            label: $proposal->summary,
        );
    }

    /**
     * Dấu vết "ai duyệt cho trợ lý làm gì".
     *
     * Các Action nghiệp vụ tự ghi audit của riêng chúng, nhưng không phải cái
     * nào cũng ghi — danh mục dịch vụ hay vật tư thì không. Một dòng ở đây bảo
     * đảm mọi thao tác qua trợ lý đều tra ngược được, kèm đúng dữ liệu đã chạy.
     *
     * `before` là bản chụp bản ghi trước khi thao tác. Với thao tác xóa cứng,
     * dòng audit này là chỗ duy nhất còn giữ nội dung đã mất, nên nó phải có
     * đủ mọi cột chứ không chỉ cái ID.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $before
     */
    private function recordApproval(
        AiActionProposal $proposal,
        ActionDefinition $action,
        User $actor,
        array $payload,
        Model $result,
        ?array $before,
    ): void {
        $this->audit->record(
            subject: $proposal,
            actor: $actor,
            action: $action->operation === 'delete' ? AuditAction::Deleted : AuditAction::Approved,
            before: $before,
            after: $payload + ['result' => $result->getMorphClass().'#'.$result->getKey()],
            reason: 'Duyệt và thực hiện qua trợ lý AI: '.$action->label.'.',
            branchId: $proposal->branch_id,
            label: $proposal->summary,
        );
    }

    /**
     * Ô trống trong HTML luôn về dưới dạng chuỗi rỗng; validate cần null để
     * phân biệt "bỏ trống" với "điền số 0" hay "điền chuỗi rỗng".
     */
    private function normalise(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_values(array_filter(
                array_map(fn (mixed $item): mixed => is_string($item) ? trim($item) : $item, $value),
                fn (mixed $item): bool => $item !== '' && $item !== null,
            ));
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }

    private function safeFailureMessage(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return implode(' ', array_map(
                fn (array $messages): string => implode(' ', $messages),
                $exception->errors()
            ));
        }

        return 'Thao tác thất bại. Vui lòng thử lại hoặc liên hệ hỗ trợ.';
    }
}
