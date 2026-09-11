<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Every seeder below matches on a natural key and updates in place, so
     * running this against an environment that already holds data tops it up
     * rather than duplicating it.
     */
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            BranchCatalogSeeder::class,
            StaffSeeder::class,
            PayrollPolicySeeder::class,
        ]);
    }
}
