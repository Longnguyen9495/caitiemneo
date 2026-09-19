<?php

namespace App\Services\Ai;

use App\Enums\AiActionStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Ai\Contracts\AiProvider;
use App\Support\BranchContext;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiConversationService
{
    public function __construct(
        private AiProvider $provider,
        private AiPromptBuilder $prompts,
        private BranchContext $branches,
    ) {}

    public function createConversation(User $user): AiConversation
    {
        return AiConversation::query()->create([
            'user_id' => $user->id,
            'branch_id' => $this->branches->currentId(),
            'scope_branch_ids' => $this->branches->scopeIds(),
            'last_message_at' => now(),
        ]);
    }

    /**
     * Ghi câu hỏi của người dùng trước khi gọi provider.
     *
     * Tách riêng để luồng phát trực tiếp lưu được câu hỏi ngay, rồi mới mở kết
     * nối dài với provider — hỏng giữa chừng thì câu hỏi vẫn còn trong lịch sử.
     */
    public function recordQuestion(AiConversation $conversation, string $question): void
    {
        DB::transaction(function () use ($conversation, $question): void {
            $locked = AiConversation::query()->lockForUpdate()->findOrFail($conversation->id);

            $locked->messages()->create([
                'role' => 'user',
                'content' => $question,
            ]);

            $locked->forceFill([
                'title' => $locked->title ?: Str::limit($question, 80, ''),
                'branch_id' => $this->branches->currentId(),
                'scope_branch_ids' => $this->branches->scopeIds(),
                'last_message_at' => now(),
            ])->save();
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function buildMessages(User $user, AiConversation $conversation, string $question): array
    {
        return $this->prompts->build($user, $conversation, $question);
    }

    /**
     * Một lượt hỏi đáp trọn vẹn.
     *
     * @param  Closure(AiStreamEvent): void|null  $onEvent
     */
    public function reply(
        User $user,
        AiConversation $conversation,
        string $question,
        ?Closure $onEvent = null,
    ): AiMessage {
        $messages = $this->buildMessages($user, $conversation, $question);

        $this->recordQuestion($conversation, $question);

        $result = $this->provider->converse($messages, $user, $onEvent);

        return $this->storeAnswer($user, $conversation, $result);
    }

    /**
     * Lưu câu trả lời cùng các đề xuất thao tác đi kèm.
     */
    public function storeAnswer(User $user, AiConversation $conversation, AiProviderResult $result): AiMessage
    {
        return DB::transaction(function () use ($user, $conversation, $result): AiMessage {
            $locked = AiConversation::query()->lockForUpdate()->findOrFail($conversation->id);

            $assistant = $locked->messages()->create([
                'role' => 'assistant',
                'content' => $result->content,
                'blocks' => $result->blocks,
                'provider' => $result->provider,
                'model' => $result->model,
                'prompt_tokens' => $result->promptTokens,
                'completion_tokens' => $result->completionTokens,
                'total_tokens' => $result->totalTokens,
                'latency_ms' => $result->latencyMs,
                'provider_reference' => $result->providerReference,
                'metadata' => [
                    'tools_used' => $result->toolsUsed,
                    'data_digest' => $this->digest($result),
                ],
            ]);

            foreach ($result->actions as $action) {
                $payload = $action['payload'];
                $branchId = is_numeric($payload['branch_id'] ?? null) ? (int) $payload['branch_id'] : null;
                $fingerprint = hash('sha256', json_encode([
                    'type' => $action['type'],
                    'payload' => $this->sortRecursively($payload),
                    'user_id' => $user->id,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

                $assistant->actionProposals()->create([
                    'proposed_by' => $user->id,
                    'branch_id' => $branchId,
                    'type' => $action['type'],
                    'summary' => $action['summary'],
                    'payload' => $payload,
                    'status' => AiActionStatus::Pending,
                    'idempotency_key' => (string) Str::uuid(),
                    'request_fingerprint' => $fingerprint,
                ]);
            }

            $locked->forceFill(['last_message_at' => now()])->save();

            return $assistant->load('actionProposals');
        });
    }

    /**
     * Tóm tắt một dòng về số liệu đã dùng, để lượt sau còn bám vào.
     *
     * Chỉ ghi tên bước và khoảng thời gian, không ghi số liệu thật: số liệu thay
     * đổi theo thời gian, còn "đã xem doanh thu khoảng nào" thì vẫn đúng về sau.
     */
    private function digest(AiProviderResult $result): string
    {
        if ($result->toolsUsed === []) {
            return '';
        }

        $parts = [];

        foreach ($result->toolsUsed as $use) {
            $tool = (string) ($use['tool'] ?? '');
            $arguments = is_array($use['arguments'] ?? null) ? $use['arguments'] : [];
            $from = $arguments['from'] ?? null;
            $to = $arguments['to'] ?? null;

            $parts[] = is_string($from) && is_string($to)
                ? "{$tool} ({$from} → {$to})"
                : $tool;
        }

        return Str::limit(implode(', ', array_unique($parts)), 300);
    }

    /** @param array<string, mixed> $value */
    private function sortRecursively(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursively($item);
            }
        }

        return $value;
    }
}
