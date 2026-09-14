<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            $table->unsignedSmallInteger('calendar_days')->nullable()->after('base_salary');
            $table->unsignedSmallInteger('required_work_days')->nullable()->after('calendar_days');
            $table->unsignedSmallInteger('paid_leave_days')->nullable()->after('required_work_days');
            $table->unsignedSmallInteger('unpaid_leave_days')->nullable()->after('paid_leave_days');
            $table->decimal('daily_base_salary_rate', 14, 2)->nullable()->after('unpaid_leave_days');
            $table->decimal('unpaid_leave_deduction', 14, 2)->nullable()->after('daily_base_salary_rate');
            $table->unsignedSmallInteger('worked_paid_leave_days')->nullable()->after('unpaid_leave_deduction');
            $table->decimal('worked_paid_leave_bonus_rate', 14, 2)->nullable()->after('worked_paid_leave_days');
            $table->decimal('worked_paid_leave_bonus', 14, 2)->nullable()->after('worked_paid_leave_bonus_rate');
            $table->decimal('net_base_salary', 14, 2)->nullable()->after('worked_paid_leave_bonus');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            $table->dropColumn([
                'calendar_days',
                'required_work_days',
                'paid_leave_days',
                'unpaid_leave_days',
                'daily_base_salary_rate',
                'unpaid_leave_deduction',
                'worked_paid_leave_days',
                'worked_paid_leave_bonus_rate',
                'worked_paid_leave_bonus',
                'net_base_salary',
            ]);
        });
    }
};
