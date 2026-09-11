<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

/**
 * The two shops the business runs today.
 *
 * `updateOrCreate` on the code keeps the seeder safe to re-run and stops it
 * duplicating the default branch the upgrade migration already created.
 */
class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::query()->updateOrCreate(
            ['code' => 'CN-01'],
            [
                'name' => 'Cái Tiệm Neo Quận 1',
                'address' => '12 Nguyễn Huệ, Quận 1, TP.HCM',
                'phone' => '02838220101',
                'is_active' => true,
            ],
        );

        Branch::query()->updateOrCreate(
            ['code' => 'CN-02'],
            [
                'name' => 'Cái Tiệm Neo Thảo Điền',
                'address' => '45 Xuân Thủy, Thảo Điền, TP.HCM',
                'phone' => '02838220202',
                'is_active' => true,
            ],
        );
    }
}
