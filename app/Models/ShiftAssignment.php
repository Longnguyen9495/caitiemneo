<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One employee rostered onto one shift on one date.
 *
 * The planned times, name, value and grace are snapshots taken when the roster
 * was written. Editing the template afterwards must not move a shift that has
 * already been worked, so nothing here reads back through `work_shift_id`
 * except for display of which template it came from.
 */
#[Fillable([
    'branch_id',
    'employee_id',
    'work_shift_id',
    'work_date',
    'shift_name',
    'planned_start_at',
    'planned_end_at',
    'shift_value',
    'grace_minutes',
    'early_check_in_minutes',
    'note',
    'created_by',
])]
class ShiftAssignment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'shift_value' => 'decimal:2',
            'grace_minutes' => 'integer',
            'early_check_in_minutes' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The attendance row produced from this roster entry, if any.
     *
     * Matching on the shift assignment rather than the date keeps split shifts
     * and overnight shifts unambiguous.
     */
    public function attendanceRecord(): HasOne
    {
        return $this->hasOne(AttendanceRecord::class, 'shift_assignment_id');
    }

    public function shiftRequests(): HasMany
    {
        return $this->hasMany(ShiftRequest::class);
    }

    public function counterShiftRequests(): HasMany
    {
        return $this->hasMany(ShiftRequest::class, 'counter_shift_assignment_id');
    }

    public function replacement(): HasOne
    {
        return $this->hasOne(ShiftReplacement::class, 'original_shift_assignment_id');
    }

    public function scopeForDate(Builder $query, mixed $date): Builder
    {
        return $query->whereDate('work_date', $date);
    }

    public function scopeBetweenDates(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query->whereBetween('work_date', [$from, $to]);
    }

    public function scopeForEmployee(Builder $query, User|int $employee): Builder
    {
        return $query->where('employee_id', $employee instanceof User ? $employee->getKey() : $employee);
    }

    /**
     * Rosters covering any minute of a window.
     *
     * Every "is this person already busy" question in the application asks it
     * this way. Comparing the snapshot window rather than `work_date` is what
     * makes the answer right for an overnight shift, which belongs to one
     * business date but runs into the next.
     */
    public function scopeOverlapping(Builder $query, mixed $start, mixed $end): Builder
    {
        return $query
            ->where('planned_start_at', '<', $end)
            ->where('planned_end_at', '>', $start);
    }

    /** Rosters covering the same window as another entry. */
    public function scopeOverlappingAssignment(Builder $query, self $assignment): Builder
    {
        return $query->overlapping($assignment->planned_start_at, $assignment->planned_end_at);
    }

    /** The earliest moment the employee may clock in for this shift. */
    public function earliestCheckInAt(): CarbonInterface
    {
        $window = $this->early_check_in_minutes ?? (int) config('attendance.early_check_in_minutes', 30);

        return $this->planned_start_at->copy()->subMinutes($window);
    }

    /** After this the shift is stale and only a manager may record it. */
    public function latestCheckInAt(): CarbonInterface
    {
        return $this->planned_end_at->copy()->addMinutes((int) config('attendance.late_check_in_grace_minutes', 60));
    }

    public function crossesMidnight(): bool
    {
        return $this->planned_end_at->toDateString() !== $this->planned_start_at->toDateString();
    }

    public function timeRangeLabel(): string
    {
        return $this->planned_start_at->format('H:i').' – '.$this->planned_end_at->format('H:i')
            .($this->crossesMidnight() ? ' (+1 ngày)' : '');
    }

    /**
     * How one rostered shift reads in a dropdown: date, shift name, hours.
     *
     * Every option list that offers a shift spells it out the same way, so two
     * lists on the same screen cannot describe the same shift differently.
     */
    public function scheduleLabel(): string
    {
        return sprintf('%s · %s (%s)', $this->work_date->format('d/m/Y'), $this->shift_name, $this->timeRangeLabel());
    }
}
