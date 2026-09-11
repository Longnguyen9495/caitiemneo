<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\EmployeeBranchAssignment;
use App\Models\EmployeeCompensationProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Start date stamped on the posting this factory creates by default.
     *
     * It doubles as a marker: {@see atBranch()} and {@see withoutBranch()}
     * recognise and remove it, so an explicit posting replaces the default one
     * instead of stacking on top of it.
     */
    public const DEFAULT_POSTING_DATE = '1990-01-01';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Employee,
            'phone' => fake()->numerify('09########'),
            'base_salary' => 0,
            'shift_rate' => 0,
            'commission_rate' => 0,
            'can_manage_appointments' => false,
            'can_create_invoices' => false,
            'can_manage_payroll' => false,
            'is_active' => true,
        ];
    }

    /**
     * Every account is posted to a branch by default, mirroring the upgrade
     * migration which gave the existing staff the default branch.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->branchAssignments()->exists()) {
                return;
            }

            EmployeeBranchAssignment::query()->create([
                'branch_id' => BranchFactory::resolveId(),
                'user_id' => $user->getKey(),
                'is_primary' => true,
                'starts_on' => self::DEFAULT_POSTING_DATE,
            ]);
        })->afterCreating(function (User $user): void {
            // Mirrors the upgrade migration: the rates on the account become the
            // employee's opening compensation profile, which is what payroll reads.
            if ($user->compensationProfiles()->exists()) {
                return;
            }

            EmployeeCompensationProfile::query()->create([
                'user_id' => $user->getKey(),
                'branch_id' => null,
                'base_salary' => $user->base_salary,
                'shift_rate' => $user->shift_rate,
                'regular_commission_rate' => $user->commission_rate,
                'overtime_commission_rate' => $user->commission_rate,
                'effective_from' => '2000-01-01',
            ]);
        });
    }

    /** An account deliberately left without any branch posting. */
    public function withoutBranch(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->branchAssignments()->whereDate('starts_on', self::DEFAULT_POSTING_DATE)->delete();
        });
    }

    /**
     * Post the account to one specific branch.
     *
     * The default posting the factory adds is dropped first, so the account
     * ends up exactly where the test says it works and nowhere else.
     */
    public function atBranch(int|Branch $branch, bool $isPrimary = true, string $startsOn = '2000-01-01', ?string $endsOn = null): static
    {
        $branchId = $branch instanceof Branch ? $branch->getKey() : $branch;

        return $this->afterCreating(function (User $user) use ($branchId, $isPrimary, $startsOn, $endsOn): void {
            $user->branchAssignments()->whereDate('starts_on', self::DEFAULT_POSTING_DATE)->delete();

            EmployeeBranchAssignment::query()->updateOrCreate(
                ['user_id' => $user->getKey(), 'branch_id' => $branchId, 'starts_on' => $startsOn],
                ['is_primary' => $isPrimary, 'ends_on' => $endsOn],
            );
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => UserRole::Owner]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => UserRole::Manager]);
    }

    public function employee(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => UserRole::Employee]);
    }

    /** A manager the owner has explicitly trusted with salaries. */
    public function payrollManager(): static
    {
        return $this->manager()->state(fn (array $attributes): array => ['can_manage_payroll' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
