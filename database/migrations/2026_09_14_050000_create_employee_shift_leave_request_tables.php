<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_fixed_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_shift_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from', 'effective_to'], 'efs_employee_window_index');
            $table->index(['branch_id', 'effective_from'], 'efs_branch_window_index');
        });

        Schema::create('monthly_paid_leave_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('leave_date');
            $table->foreignId('scheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'leave_date'], 'mpld_employee_date_unique');
            $table->index(['branch_id', 'leave_date'], 'mpld_branch_date_index');
        });

        Schema::create('shift_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->string('status', 30);
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shift_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('counter_shift_assignment_id')->nullable()->constrained('shift_assignments')->restrictOnDelete();
            $table->date('work_date');
            $table->text('reason')->nullable();
            $table->string('leave_entitlement', 20)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'work_date'], 'shift_requests_branch_status_date_index');
            $table->index(['requester_id', 'status'], 'shift_requests_requester_status_index');
            $table->index(['recipient_id', 'status'], 'shift_requests_recipient_status_index');
            $table->index(['shift_assignment_id', 'status'], 'shift_requests_assignment_status_index');
        });

        Schema::create('shift_request_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shift_request_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['shift_request_id', 'id'], 'srh_request_id_index');
        });

        Schema::create('shift_replacements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shift_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('original_shift_assignment_id')->constrained('shift_assignments')->restrictOnDelete();
            $table->foreignId('replacement_employee_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('replacement_shift_assignment_id')->nullable()->constrained('shift_assignments')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique('original_shift_assignment_id', 'shift_replacements_original_unique');
            $table->unique('replacement_shift_assignment_id', 'shift_replacements_shift_unique');
            $table->index(['replacement_employee_id', 'created_at'], 'shift_replacements_employee_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_replacements');
        Schema::dropIfExists('shift_request_histories');
        Schema::dropIfExists('shift_requests');
        Schema::dropIfExists('monthly_paid_leave_days');
        Schema::dropIfExists('employee_fixed_shifts');
    }
};
