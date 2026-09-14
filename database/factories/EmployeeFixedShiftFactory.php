<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\EmployeeFixedShift;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployeeFixedShift> */
class EmployeeFixedShiftFactory extends Factory
{
    protected $model = EmployeeFixedShift::class;

    public function definition(): array
    {
        return [
            'employee_id' => User::factory()->employee(),
            'branch_id' => Branch::factory(),
            'work_shift_id' => WorkShift::factory(),
            'effective_from' => now()->startOfMonth()->toDateString(),
            'effective_to' => null,
        ];
    }
}
