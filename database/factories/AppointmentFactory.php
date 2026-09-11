<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startsAt = Carbon::parse(fake()->dateTimeBetween('-1 month', '+1 month'))->startOfHour();
        $duration = fake()->randomElement([30, 45, 60, 90]);

        return [
            'branch_id' => BranchFactory::existingOrNew(),
            'customer_id' => Customer::factory(),
            'employee_id' => null,
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('09########'),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($duration),
            'duration_minutes' => $duration,
            'status' => AppointmentStatus::Pending,
            'note' => null,
        ];
    }

    public function at(Carbon $startsAt, int $durationMinutes = 60): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($durationMinutes),
            'duration_minutes' => $durationMinutes,
        ]);
    }

    public function status(AppointmentStatus $status): static
    {
        return $this->state(fn (array $attributes): array => ['status' => $status]);
    }
}
