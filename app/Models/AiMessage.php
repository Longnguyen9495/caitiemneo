<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'conversation_id',
    'role',
    'content',
    'blocks',
    'provider',
    'model',
    'prompt_tokens',
    'completion_tokens',
    'total_tokens',
    'latency_ms',
    'provider_reference',
    'metadata',
])]
class AiMessage extends Model
{
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'metadata' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function actionProposals(): HasMany
    {
        return $this->hasMany(AiActionProposal::class, 'message_id');
    }
}
