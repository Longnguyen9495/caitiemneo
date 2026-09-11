<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dated compensation history and payroll policy configuration.
 *
 * The rates on `users` stop being the source of truth for payroll: they are
 * copied into a first profile here and everything the engine reads from now on
 * is a dated row, so a raise never rewrites a payroll that was already closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_compensation_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('base_salary', 14, 2)->default(0);
            $table->decimal('shift_rate', 14, 2)->default(0);
            $table->decimal('regular_commission_rate', 5, 2)->default(0);
            $table->decimal('overtime_commission_rate', 5, 2)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'branch_id', 'effective_from', 'effective_to'], 'ecp_user_branch_window_index');
        });

        Schema::create('payroll_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('attendance_bonus_amount', 14, 2)->default(0);
            $table->unsignedSmallInteger('allowed_absence_days')->default(0);
            $table->boolean('excused_leave_counts_as_absence')->default(false);
            $table->boolean('late_counts_as_absence')->default(false);
            $table->unsignedSmallInteger('required_bill_count')->nullable();
            $table->decimal('bill_kpi_reward_amount', 14, 2)->nullable();
            $table->decimal('missing_bill_penalty_amount', 14, 2)->nullable();
            $table->string('rounding_rule', 30)->default('ceil_to_1000');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'effective_from', 'effective_to'], 'payroll_policies_window_index');
        });

        Schema::create('daily_kpi_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_policy_id')->constrained()->cascadeOnDelete();
            $table->decimal('revenue_from', 14, 2);
            $table->decimal('revenue_to', 14, 2)->nullable();
            $table->decimal('reward_amount', 14, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['payroll_policy_id', 'sort_order']);
        });

        Schema::create('employee_policy_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_policy_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'effective_from', 'effective_to'], 'epa_user_window_index');
        });

        $this->backfillProfilesFromUsers();
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_policy_assignments');
        Schema::dropIfExists('daily_kpi_tiers');
        Schema::dropIfExists('payroll_policies');
        Schema::dropIfExists('employee_compensation_profiles');
    }

    /** Freeze today's rates as each employee's opening profile. */
    private function backfillProfilesFromUsers(): void
    {
        DB::table('users')
            ->select('id', 'base_salary', 'shift_rate', 'commission_rate', 'created_at')
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $exists = DB::table('employee_compensation_profiles')
                        ->where('user_id', $user->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('employee_compensation_profiles')->insert([
                        'user_id' => $user->id,
                        'branch_id' => null,
                        'base_salary' => $user->base_salary ?? 0,
                        'shift_rate' => $user->shift_rate ?? 0,
                        'regular_commission_rate' => $user->commission_rate ?? 0,
                        'overtime_commission_rate' => $user->commission_rate ?? 0,
                        'effective_from' => $user->created_at ? Carbon\Carbon::parse($user->created_at)->toDateString() : '2000-01-01',
                        'effective_to' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }
};
