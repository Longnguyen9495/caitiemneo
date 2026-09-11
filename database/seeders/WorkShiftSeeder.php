<?php

namespace Database\Seeders;

use App\Models\WorkShift;
use Illuminate\Database\Seeder;

/**
 * The shift templates the shop actually runs.
 *
 * They are seeded company-wide (`branch_id` null) because both shops open on
 * the same hours; a branch that diverges later gets its own template rather
 * than an edit to these.
 *
 * `firstOrCreate`, not `updateOrCreate`: re-running must not reset a grace
 * period or a shift value that a manager has since tuned.
 */
class WorkShiftSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['name' => 'Ca 09:00 – 19:00', 'starts_at' => '09:00:00', 'ends_at' => '19:00:00', 'shift_value' => 1],
            ['name' => 'Ca 10:00 – 20:00', 'starts_at' => '10:00:00', 'ends_at' => '20:00:00', 'shift_value' => 1],
            ['name' => 'Ca 11:00 – 21:00', 'starts_at' => '11:00:00', 'ends_at' => '21:00:00', 'shift_value' => 1],
            ['name' => 'Ca dài 09:00 – 20:00', 'starts_at' => '09:00:00', 'ends_at' => '20:00:00', 'shift_value' => 1.1],
        ];

        foreach ($templates as $template) {
            WorkShift::query()->firstOrCreate(
                ['branch_id' => null, 'name' => $template['name']],
                $template + [
                    // Nhân viên không được đi muộn, nên ân hạn mặc định là 0.
                    // Quản lý có thể nới ở màn hình danh mục ca nếu cần.
                    'grace_minutes' => 0,
                    'early_check_in_minutes' => null,
                    'is_active' => true,
                ],
            );
        }
    }
}
