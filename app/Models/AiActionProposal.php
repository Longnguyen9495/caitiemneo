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

    /**
     * Dấu vân tay của một đề xuất: cùng người, cùng thao tác, cùng dữ liệu thì
     * ra cùng một chuỗi. Người duyệt sửa lại phiếu trước khi duyệt thì dấu này
     * phải tính lại, nếu không hai đề xuất khác nhau lại mang chung một vân tay.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fingerprintFor(string $type, array $payload, int $userId): string
    {
        return hash('sha256', json_encode([
            'type' => $type,
            'payload' => self::sortRecursively($payload),
            'user_id' => $userId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private static function sortRecursively(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortRecursively($item);
            }
        }

        return $value;
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
