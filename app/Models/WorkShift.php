<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shift template: "Ca sáng, 09:00 đến 19:00, ân hạn 5 phút".
 *
 * A null `branch_id` means the template is shared by every shop. Templates are
 * never deleted, only deactivated, because rosters already reference them.
 */
#[Fillable([
    'branch_id',
    'name',
    'starts_at',
    'ends_at',
    'shift_value',
    'grace_minutes',
    'early_check_in_minutes',
    'is_active',
])]
class WorkShift extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'shift_value' => 'decimal:2',
            'grace_minutes' => 'integer',
            'early_check_in_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Templates usable at a branch: its own, plus the company-wide ones. */
    public function scopeUsableAt(Builder $query, array|int|null $branchIds): Builder
    {
        $ids = array_filter((array) $branchIds);

        return $query->where(fn (Builder $inner) => $inner
            ->whereNull('branch_id')
            ->when($ids !== [], fn (Builder $q) => $q->orWhereIn('branch_id', $ids)));
    }

    /** A shift that ends at or before it starts runs past midnight. */
    public function crossesMidnight(): bool
    {
        return $this->minutesFromMidnight('ends_at') <= $this->minutesFromMidnight('starts_at');
    }

    /** Planned start for one business date, as a concrete moment. */
    public function plannedStartOn(CarbonInterface|string $workDate): CarbonImmutable
    {
        return CarbonImmutable::parse($workDate)
            ->startOfDay()
            ->addMinutes($this->minutesFromMidnight('starts_at'));
    }

    /** Planned end for one business date, rolled to the next day when needed. */
    public function plannedEndOn(CarbonInterface|string $workDate): CarbonImmutable
    {
        $end = CarbonImmutable::parse($workDate)
            ->startOfDay()
            ->addMinutes($this->minutesFromMidnight('ends_at'));

        return $this->crossesMidnight() ? $end->addDay() : $end;
    }

    public function timeRangeLabel(): string
    {
        $suffix = $this->crossesMidnight() ? ' (+1 ngày)' : '';

        return $this->formatTime('starts_at').' – '.$this->formatTime('ends_at').$suffix;
    }

    public function label(): string
    {
        return $this->name.' · '.$this->timeRangeLabel();
    }

    public function formatTime(string $attribute): string
    {
        return sprintf('%02d:%02d', intdiv($this->minutesFromMidnight($attribute), 60), $this->minutesFromMidnight($attribute) % 60);
    }

    /**
     * Minutes past midnight for a time column.
     *
     * The driver hands back "09:00:00" on MySQL and may hand back a full
     * datetime string on SQLite, so the value is parsed rather than trusted.
     */
    private function minutesFromMidnight(string $attribute): int
    {
        $raw = $this->getAttributeValue($attribute);

        if ($raw === null) {
            return 0;
        }

        if (preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?$/', (string) $raw, $matches) !== 1) {
            return 0;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }
}
