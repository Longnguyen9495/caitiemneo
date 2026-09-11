<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commission owed back after a refund that lands on an already closed payroll.
 *
 * A finalised payroll is a snapshot and must never move, so the money is parked
 * here and picked up by the employee's next draft payroll as a correction line.
 * The unique key on the source makes re-processing the same refund a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_payroll_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 20);
            $table->decimal('amount', 14, 2);
            $table->string('description');
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('origin_payroll_id')->nullable()->constrained('payrolls')->nullOnDelete();
            $table->foreignId('applied_payroll_id')->nullable()->constrained('payrolls')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'employee_id'], 'ppc_source_employee_unique');
            $table->index(['employee_id', 'applied_payroll_id'], 'ppc_employee_applied_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_payroll_corrections');
    }
};
