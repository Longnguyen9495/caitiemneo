<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns the new pay rules read from: the work context of every revenue line,
 * the bill-KPI verification stamp, and the audited rounding of a payroll.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->string('work_context', 20)->default('regular')->after('employee_id');
            $table->string('commission_rate_source', 30)->nullable()->after('commission_rate');
            $table->string('commission_rate_reason')->nullable()->after('commission_rate_source');
            $table->foreignId('overtime_approved_by')->nullable()->after('commission_rate_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('overtime_approved_at')->nullable()->after('overtime_approved_by');

            $table->index(['work_context'], 'invoice_items_work_context_index');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->boolean('qualified_for_bill_kpi')->default(false)->after('cancel_reason');
            $table->foreignId('bill_kpi_verified_by')->nullable()->after('qualified_for_bill_kpi')->constrained('users')->nullOnDelete();
            $table->timestamp('bill_kpi_verified_at')->nullable()->after('bill_kpi_verified_by');
            $table->string('bill_kpi_note')->nullable()->after('bill_kpi_verified_at');

            $table->index(['qualified_for_bill_kpi', 'paid_at'], 'invoices_bill_kpi_index');
        });

        Schema::table('payrolls', function (Blueprint $table): void {
            $table->foreignId('paying_branch_id')->nullable()->after('employee_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('payroll_policy_id')->nullable()->after('paying_branch_id')->constrained()->nullOnDelete();
            $table->decimal('attendance_bonus', 14, 2)->default(0)->after('commission_pay');
            $table->decimal('regular_revenue', 14, 2)->default(0)->after('attendance_bonus');
            $table->decimal('regular_commission_pay', 14, 2)->default(0)->after('regular_revenue');
            $table->decimal('overtime_revenue', 14, 2)->default(0)->after('regular_commission_pay');
            $table->decimal('overtime_commission_pay', 14, 2)->default(0)->after('overtime_revenue');
            $table->decimal('daily_kpi_bonus', 14, 2)->default(0)->after('overtime_commission_pay');
            $table->decimal('bill_kpi_bonus', 14, 2)->default(0)->after('daily_kpi_bonus');
            $table->unsignedSmallInteger('qualified_bill_count')->default(0)->after('bill_kpi_bonus');
            $table->boolean('bill_kpi_achieved')->default(false)->after('qualified_bill_count');
            $table->decimal('allowance_total', 14, 2)->default(0)->after('bill_kpi_achieved');
            $table->decimal('bonus_total', 14, 2)->default(0)->after('allowance_total');
            $table->decimal('calculated_total', 14, 2)->default(0)->after('total');
            $table->decimal('approved_manual_adjustment', 14, 2)->default(0)->after('calculated_total');
            $table->decimal('unrounded_final_total', 14, 2)->default(0)->after('approved_manual_adjustment');
            $table->decimal('rounding_adjustment', 14, 2)->default(0)->after('unrounded_final_total');
            $table->decimal('final_total', 14, 2)->default(0)->after('rounding_adjustment');
            $table->string('rounding_rule', 30)->default('ceil_to_1000')->after('final_total');
            $table->string('variance_reason')->nullable()->after('rounding_rule');
            $table->foreignId('approved_by')->nullable()->after('variance_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('paying_branch_id');
            $table->dropConstrainedForeignId('payroll_policy_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'attendance_bonus', 'regular_revenue', 'regular_commission_pay', 'overtime_revenue',
                'overtime_commission_pay', 'daily_kpi_bonus', 'bill_kpi_bonus', 'qualified_bill_count',
                'bill_kpi_achieved', 'allowance_total', 'bonus_total', 'calculated_total',
                'approved_manual_adjustment', 'unrounded_final_total', 'rounding_adjustment',
                'final_total', 'rounding_rule', 'variance_reason', 'approved_at',
            ]);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_bill_kpi_index');
            $table->dropConstrainedForeignId('bill_kpi_verified_by');
            $table->dropColumn(['qualified_for_bill_kpi', 'bill_kpi_verified_at', 'bill_kpi_note']);
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropIndex('invoice_items_work_context_index');
            $table->dropConstrainedForeignId('overtime_approved_by');
            $table->dropColumn(['work_context', 'commission_rate_source', 'commission_rate_reason', 'overtime_approved_at']);
        });
    }
};
