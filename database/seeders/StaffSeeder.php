<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\EmployeeBranchAssignment;
use App\Models\EmployeeCompensationProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A staff list that exercises every access shape the system has to handle:
 * a company-wide owner, one manager per shop, staff tied to a single shop, and
 * one person who moved from shop A to shop B part way through the year.
 */
class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $branchA = Branch::query()->where('code', 'CN-01')->firstOrFail();
        $branchB = Branch::query()->where('code', 'CN-02')->firstOrFail();

        $owner = $this->staff('owner@caitiemneo.test', 'Chủ tiệm Neo', UserRole::Owner, [
            'base_salary' => 20000000,
            'shift_rate' => 0,
            'commission_rate' => 0,
            'can_manage_appointments' => true,
            'can_create_invoices' => true,
            'can_manage_payroll' => true,
        ]);
        $this->post($owner, $branchA, '2026-01-01');

        $managerA = $this->staff('quanly.q1@caitiemneo.test', 'Quản lý Quận 1', UserRole::Manager, [
            'base_salary' => 12000000,
            'shift_rate' => 250000,
            'commission_rate' => 5,
            'can_manage_appointments' => true,
            'can_create_invoices' => true,
        ]);
        $this->post($managerA, $branchA, '2026-01-01');

        $managerB = $this->staff('quanly.td@caitiemneo.test', 'Quản lý Thảo Điền', UserRole::Manager, [
            'base_salary' => 12000000,
            'shift_rate' => 250000,
            'commission_rate' => 5,
            'can_manage_appointments' => true,
            'can_create_invoices' => true,
            'can_manage_payroll' => true,
        ]);
        $this->post($managerB, $branchB, '2026-01-01');

        $employeeA = $this->staff('mai.q1@caitiemneo.test', 'Nguyễn Thị Mai', UserRole::Employee, [
            'base_salary' => 5000000,
            'shift_rate' => 200000,
            'commission_rate' => 15,
            'can_manage_appointments' => true,
            'can_create_invoices' => true,
        ]);
        $this->post($employeeA, $branchA, '2026-01-01');

        $employeeB = $this->staff('linh.td@caitiemneo.test', 'Trần Mỹ Linh', UserRole::Employee, [
            'base_salary' => 5000000,
            'shift_rate' => 220000,
            'commission_rate' => 15,
            'can_manage_appointments' => true,
        ]);
        $this->post($employeeB, $branchB, '2026-01-01');

        // Worked at Quận 1 for the first half of the year, then moved to Thảo Điền.
        $roving = $this->staff('ha.luanphien@caitiemneo.test', 'Phạm Thu Hà', UserRole::Employee, [
            'base_salary' => 5500000,
            'shift_rate' => 210000,
            'commission_rate' => 15,
            'can_manage_appointments' => true,
            'can_create_invoices' => true,
        ]);
        $this->post($roving, $branchA, '2026-01-01', '2026-06-30');
        $this->post($roving, $branchB, '2026-07-01', null, false);
    }

    /** @param array<string, mixed> $attributes */
    private function staff(string $email, string $name, UserRole $role, array $attributes): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            $attributes + [
                'name' => $name,
                'role' => $role,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        EmployeeCompensationProfile::query()->updateOrCreate(
            ['user_id' => $user->getKey(), 'branch_id' => null, 'effective_from' => '2026-01-01'],
            [
                'base_salary' => $attributes['base_salary'],
                'shift_rate' => $attributes['shift_rate'],
                'regular_commission_rate' => $attributes['commission_rate'],
                // Out-of-hours work pays a higher rate than normal opening hours.
                'overtime_commission_rate' => min(100, $attributes['commission_rate'] + 5),
                'effective_to' => null,
            ],
        );

        return $user;
    }

    private function post(User $user, Branch $branch, string $startsOn, ?string $endsOn = null, bool $isPrimary = true): void
    {
        EmployeeBranchAssignment::query()->updateOrCreate(
            ['user_id' => $user->getKey(), 'branch_id' => $branch->getKey(), 'starts_on' => $startsOn],
            ['is_primary' => $isPrimary, 'ends_on' => $endsOn],
        );
    }
}
