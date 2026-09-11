<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-branch payroll allocations, itemised adjustments and daily KPI results.
 *
 * A payroll stays one row per employee per period; the money is broken down per
 * branch in `payroll_allocations` so each shop carries the cost it caused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('base_salary_amount', 14, 2)->default(0);
            $table->decimal('shift_count', 8, 2)->default(0);
            $table->decimal('shift_pay', 14, 2)->default(0);
            $table->decimal('attendance_bonus', 14, 2)->default(0);
            $table->decimal('regular_revenue', 14, 2)->default(0);
            $table->decimal('regular_commission_pay', 14, 2)->default(0);
            $table->decimal('overtime_revenue', 14, 2)->default(0);
            $table->decimal('overtime_commission_pay', 14, 2)->default(0);
            $table->decimal('daily_kpi_bonus', 14, 2)->default(0);
            $table->decimal('bill_kpi_bonus', 14, 2)->default(0);
            $table->decimal('allowance_total', 14, 2)->default(0);
            $table->decimal('bonus_total', 14, 2)->default(0);
            $table->decimal('deduction_total', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['payroll_id', 'branch_id']);
        });

        Schema::create('payroll_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_allocation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 40);
            $table->string('direction', 20);
            $table->decimal('amount', 14, 2);
            $table->string('description');
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->boolean('is_automatic')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['payroll_id', 'is_automatic']);
            $table->index(['payroll_id', 'category']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('daily_kpi_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_allocation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');
            $table->decimal('eligible_revenue', 14, 2)->default(0);
            $table->foreignId('achieved_tier_id')->nullable()->constrained('daily_kpi_tiers')->nullOnDelete();
            $table->decimal('reward_amount', 14, 2)->default(0);
            $table->foreignId('source_policy_id')->nullable()->constrained('payroll_policies')->nullOnDelete();
            $table->timestamps();

            $table->unique(['payroll_id', 'branch_id', 'work_date'], 'dkr_payroll_branch_date_unique');
            $table->index(['employee_id', 'branch_id', 'work_date'], 'dkr_employee_branch_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_kpi_results');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_allocations');
    }
};
