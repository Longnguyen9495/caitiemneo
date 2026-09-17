<?php

namespace App\Services\Ai;

use App\Enums\AiActionStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Ai\Contracts\AiProvider;
use App\Support\BranchContext;
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

    public function reply(User $user, AiConversation $conversation, string $question): AiMessage
    {
        $messages = $this->prompts->build($user, $conversation, $question);
        $result = $this->provider->chat($messages);

        return DB::transaction(function () use ($user, $conversation, $question, $result): AiMessage {
            $conversation = AiConversation::query()->lockForUpdate()->findOrFail($conversation->id);

            $conversation->messages()->create([
                'role' => 'user',
                'content' => $question,
            ]);

            $assistant = $conversation->messages()->create([
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

            $conversation->forceFill([
                'title' => $conversation->title ?: Str::limit($question, 80, ''),
                'branch_id' => $this->branches->currentId(),
                'scope_branch_ids' => $this->branches->scopeIds(),
                'last_message_at' => now(),
            ])->save();

            return $assistant->load('actionProposals');
        });
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
