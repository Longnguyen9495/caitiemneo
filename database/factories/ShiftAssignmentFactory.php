<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftAssignment>
 */
class ShiftAssignmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'branch_id' => BranchFactory::existingOrNew(),
            'employee_id' => User::factory()->employee(),
            'work_shift_id' => WorkShift::factory(),
            'work_date' => now()->toDateString(),
            'note' => null,
            'created_by' => null,
        ];
    }

    /**
     * Fill the snapshot from the template unless the test set it explicitly.
     *
     * Mirrors ScheduleShiftAction so a factory-built roster behaves exactly
     * like one written through the application.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ShiftAssignment $assignment): void {
            $shift = $assignment->workShift ?? WorkShift::query()->find($assignment->work_shift_id);

            if ($shift === null) {
                return;
            }

            $assignment->shift_name ??= $shift->name;
            $assignment->shift_value ??= $shift->shift_value;
            $assignment->grace_minutes ??= $shift->grace_minutes;
            $assignment->early_check_in_minutes ??= $shift->early_check_in_minutes;
            $assignment->planned_start_at ??= $shift->plannedStartOn($assignment->work_date);
            $assignment->planned_end_at ??= $shift->plannedEndOn($assignment->work_date);
        });
    }

    public function forEmployee(int|User $employee): static
    {
        return $this->state(fn (array $attributes): array => [
            'employee_id' => $employee instanceof User ? $employee->getKey() : $employee,
        ]);
    }

    public function atBranch(int|Branch $branch): static
    {
        return $this->state(fn (array $attributes): array => [
            'branch_id' => $branch instanceof Branch ? $branch->getKey() : $branch,
        ]);
    }

    public function on(string $workDate): static
    {
        return $this->state(fn (array $attributes): array => ['work_date' => $workDate]);
    }

    public function usingShift(int|WorkShift $shift): static
    {
        return $this->state(fn (array $attributes): array => [
            'work_shift_id' => $shift instanceof WorkShift ? $shift->getKey() : $shift,
        ]);
    }
}
