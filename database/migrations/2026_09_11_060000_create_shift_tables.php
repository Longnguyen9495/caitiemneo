<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shift catalogue and the weekly roster built from it.
 *
 * `shift_assignments` snapshots the planned times rather than joining back to
 * the catalogue, so editing a shift template tomorrow cannot silently rewrite
 * whether somebody was late last week.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table): void {
            // A null branch means the template is shared by the whole company.
            // MySQL treats NULLs as distinct in a unique index, so uniqueness of
            // shared names is enforced in WorkShiftRequest instead.
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->decimal('shift_value', 6, 2)->default(1);
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->unsignedSmallInteger('early_check_in_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'name'], 'work_shifts_branch_name_unique');
            $table->index(['is_active', 'starts_at'], 'work_shifts_active_start_index');
        });

        Schema::create('shift_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            // Restricted on purpose: a template that has already been rostered
            // may be deactivated but never deleted out from under history.
            $table->foreignId('work_shift_id')->constrained('work_shifts')->restrictOnDelete();
            $table->date('work_date');
            $table->string('shift_name', 60);
            // Nullable at the schema level only: MySQL refuses a second
            // `TIMESTAMP NOT NULL` column with no default, and declaring these
            // as `DATETIME` instead would mix two timezone semantics inside the
            // same comparison as `attendance_records.checked_in_at`. Every
            // write goes through ScheduleShiftAction, which always fills both.
            $table->timestamp('planned_start_at')->nullable();
            $table->timestamp('planned_end_at')->nullable();
            $table->decimal('shift_value', 6, 2)->default(1);
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->unsignedSmallInteger('early_check_in_minutes')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date', 'work_shift_id'], 'shift_assignments_employee_day_unique');
            $table->index(['branch_id', 'work_date'], 'shift_assignments_branch_day_index');
            $table->index(['employee_id', 'planned_start_at'], 'shift_assignments_employee_start_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('work_shifts');
    }
};
