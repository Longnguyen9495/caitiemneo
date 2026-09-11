<?php

namespace App\Models;

use App\Enums\RiskReviewStatus;
use App\Enums\RiskSeverity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing the rules thought was worth a second look.
 *
 * A flag is a question, never an accusation: it says "this pattern is the shape
 * fraud often takes", and a person decides whether that is what happened here.
 */
#[Fillable([
    'rule',
    'severity',
    'subject_type',
    'subject_id',
    'branch_id',
    'actor_id',
    'actor_name',
    'summary',
    'context',
    'detected_at',
    'review_status',
    'reviewed_by',
    'reviewed_at',
    'review_note',
])]
class RiskFlag extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'severity' => RiskSeverity::class,
            'review_status' => RiskReviewStatus::class,
            'context' => 'array',
            'detected_at' => 'datetime',
            'reviewed_at' => 'datetime',
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('review_status', RiskReviewStatus::Open->value);
    }

    public function scopeForBranches(Builder $query, array $branchIds): Builder
    {
        return $query->whereIn('branch_id', $branchIds);
    }

    public function isOpen(): bool
    {
        return $this->review_status === RiskReviewStatus::Open;
    }

    /** Who acted, falling back to the name snapshot when the account is gone. */
    public function actorLabel(): string
    {
        return $this->actor?->name ?? $this->actor_name ?? 'Không xác định';
    }
}
