<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'branch_id' => BranchFactory::existingOrNew(),
            'employee_id' => User::factory(),
            'work_date' => now()->toDateString(),
            'shift_name' => 'Ca chính',
            'shift_value' => 1,
            'status' => AttendanceStatus::Present,
            'checked_in_at' => null,
            'checked_out_at' => null,
            'note' => null,
        ];
    }

    public function status(AttendanceStatus $status): static
    {
        return $this->state(fn (array $attributes): array => ['status' => $status]);
    }
}
