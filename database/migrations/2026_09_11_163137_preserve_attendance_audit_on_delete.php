<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stop a deleted shift from taking its own audit trail with it.
 *
 * `attendance_audit_logs.attendance_record_id` was created with a cascading
 * delete, so removing a shift erased the evidence of who had written it and
 * why — precisely the record an investigation needs, and precisely the row a
 * manager covering their tracks would want gone.
 *
 * The column becomes nullable with `nullOnDelete`, and the identifying details
 * of the shift are copied into new snapshot columns so an orphaned entry is
 * still readable on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_audit_logs', function (Blueprint $table): void {
            // Snapshot of the subject, so the entry survives readable.
            $table->unsignedBigInteger('employee_id_snapshot')->nullable()->after('attendance_record_id');
            $table->date('work_date_snapshot')->nullable()->after('employee_id_snapshot');
            $table->string('shift_name_snapshot', 60)->nullable()->after('work_date_snapshot');
        });

        // Backfill from the shifts still present, so existing history gains the
        // same standalone detail new entries will carry.
        $this->backfillSnapshots();

        Schema::table('attendance_audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['attendance_record_id']);
            $table->unsignedBigInteger('attendance_record_id')->nullable()->change();
            $table->foreign('attendance_record_id')
                ->references('id')
                ->on('attendance_records')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Rows orphaned while the new behaviour was live have no shift to point
        // at, so they are detached rather than deleted: reversing a migration
        // must not destroy audit history either.
        Schema::table('attendance_audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['attendance_record_id']);
            $table->foreign('attendance_record_id')
                ->references('id')
                ->on('attendance_records')
                ->cascadeOnDelete();

            $table->dropColumn(['employee_id_snapshot', 'work_date_snapshot', 'shift_name_snapshot']);
        });
    }

    private function backfillSnapshots(): void
    {
        DB::table('attendance_audit_logs')
            ->whereNull('shift_name_snapshot')
            ->whereNotNull('attendance_record_id')
            ->orderBy('id')
            ->chunkById(500, function ($entries): void {
                foreach ($entries as $entry) {
                    $record = DB::table('attendance_records')
                        ->where('id', $entry->attendance_record_id)
                        ->first(['employee_id', 'work_date', 'shift_name']);

                    if ($record === null) {
                        continue;
                    }

                    DB::table('attendance_audit_logs')
                        ->where('id', $entry->id)
                        ->update([
                            'employee_id_snapshot' => $record->employee_id,
                            'work_date_snapshot' => $record->work_date,
                            'shift_name_snapshot' => $record->shift_name,
                        ]);
                }
            });
    }
};
