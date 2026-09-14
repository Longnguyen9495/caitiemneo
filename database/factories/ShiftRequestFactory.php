<?php

namespace Database\Factories;

use App\Enums\ShiftRequestStatus;
use App\Enums\ShiftRequestType;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShiftRequest> */
class ShiftRequestFactory extends Factory
{
    protected $model = ShiftRequest::class;

    public function definition(): array
    {
        return [
            'type' => ShiftRequestType::Leave,
            'status' => ShiftRequestStatus::PendingApproval,
            'shift_assignment_id' => ShiftAssignment::factory(),
            'reason' => $this->faker->sentence(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ShiftRequest $request): void {
            $assignment = $request->shiftAssignment
                ?? ShiftAssignment::query()->find($request->shift_assignment_id);

            if ($assignment === null) {
                return;
            }

            $request->branch_id = $assignment->branch_id;
            $request->requester_id = $assignment->employee_id;
            $request->work_date = $assignment->work_date;
        });
    }

    public function forAssignment(ShiftAssignment $assignment): static
    {
        return $this->state(fn (): array => [
            'branch_id' => $assignment->branch_id,
            'requester_id' => $assignment->employee_id,
            'shift_assignment_id' => $assignment->getKey(),
            'work_date' => $assignment->work_date,
        ]);
    }

    public function leave(): static
    {
        return $this->state([
            'type' => ShiftRequestType::Leave,
            'status' => ShiftRequestStatus::PendingApproval,
        ]);
    }

    public function swap(User $recipient, ShiftAssignment $counterAssignment): static
    {
        return $this->state([
            'type' => ShiftRequestType::Swap,
            'status' => ShiftRequestStatus::PendingRecipient,
            'recipient_id' => $recipient->getKey(),
            'counter_shift_assignment_id' => $counterAssignment->getKey(),
        ]);
    }
}
