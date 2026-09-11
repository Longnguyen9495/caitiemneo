<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns required by the invoice, cash book, inventory and payroll modules.
 *
 * Nothing here is destructive: every column is additive so the migration is safe
 * to run against a database that already holds production data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_manage_payroll')->default(false)->after('can_create_invoices');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('address');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable()->after('paid_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable()->after('cancelled_by');
        });

        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->foreignId('payroll_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
            $table->foreignId('reverses_transaction_id')->nullable()->after('payroll_id')->constrained('cash_transactions')->nullOnDelete();
            $table->string('idempotency_key', 120)->nullable()->after('reference')->unique();
            $table->timestamp('voided_at')->nullable()->after('occurred_at');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable()->after('voided_by');
        });

        Schema::table('payrolls', function (Blueprint $table): void {
            $table->decimal('shift_rate', 14, 2)->default(0)->after('base_salary');
            $table->decimal('shift_count', 8, 2)->default(0)->after('shift_rate');
            $table->foreignId('created_by')->nullable()->after('employee_id')->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->after('paid_at');
            $table->foreignId('finalized_by')->nullable()->after('finalized_at')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('finalized_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['shift_rate', 'shift_count', 'finalized_at', 'cancelled_at']);
        });

        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payroll_id');
            $table->dropConstrainedForeignId('reverses_transaction_id');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'voided_at', 'void_reason']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancel_reason']);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('can_manage_payroll');
        });
    }
};
