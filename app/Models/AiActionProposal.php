<?php

namespace App\Models;

use App\Enums\AiActionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'message_id',
    'proposed_by',
    'branch_id',
    'type',
    'summary',
    'payload',
    'status',
    'idempotency_key',
    'request_fingerprint',
    'decided_by',
    'decided_at',
    'result_type',
    'result_id',
    'failure_message',
])]
class AiActionProposal extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => AiActionStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'message_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function result(): MorphTo
    {
        return $this->morphTo();
    }
}
