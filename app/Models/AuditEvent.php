<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One append-only entry in the company-wide audit trail.
 *
 * The subject is referenced loosely rather than by foreign key so that the
 * event outlives the row it describes; see the migration for why that matters.
 */
#[Fillable([
    'auditable_type',
    'auditable_id',
    'auditable_label',
    'action',
    'branch_id',
    'actor_id',
    'actor_name',
    'reason',
    'before',
    'after',
    'correlation_id',
    'ip_hash',
    'user_agent',
])]
class AuditEvent extends Model
{
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** Events about one subject, oldest first. */
    public function scopeForSubject(Builder $query, Model|string $type, ?int $id = null): Builder
    {
        if ($type instanceof Model) {
            $id = $type->getKey();
            $type = $type->getMorphClass();
        }

        return $query
            ->where('auditable_type', $type)
            ->when($id !== null, fn (Builder $inner) => $inner->where('auditable_id', $id))
            ->orderBy('id');
    }

    /**
     * Fields that actually moved, for rendering a compact diff.
     *
     * @return array<string, array{before: mixed, after: mixed}>
     */
    public function changes(): array
    {
        $before = $this->before ?? [];
        $after = $this->after ?? [];
        $diff = [];

        foreach (array_keys($before + $after) as $key) {
            $from = $before[$key] ?? null;
            $to = $after[$key] ?? null;

            if ($from !== $to) {
                $diff[$key] = ['before' => $from, 'after' => $to];
            }
        }

        return $diff;
    }

    /** Who did it, falling back to the name snapshot when the account is gone. */
    public function actorLabel(): string
    {
        return $this->actor?->name ?? $this->actor_name ?? 'Không xác định';
    }
}
