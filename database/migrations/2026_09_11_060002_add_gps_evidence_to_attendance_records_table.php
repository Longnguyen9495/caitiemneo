<?php

use App\Enums\AttendanceSource;
use App\Enums\OvertimeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clock-in evidence, lateness and overtime on the existing attendance row.
 *
 * Every column is nullable or defaulted, so the rows written by the manual
 * admin form before this upgrade stay valid and keep behaving exactly as they
 * did: `manual` source, no GPS evidence, no overtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->foreignId('shift_assignment_id')->nullable()->after('branch_id')
                ->constrained('shift_assignments')->nullOnDelete();
            $table->string('source', 20)->default(AttendanceSource::Manual->value)->after('shift_value');

            $this->addClockEvidence($table, 'check_in', 'checked_in_at');
            $this->addClockEvidence($table, 'check_out', 'checked_out_at');

            $table->unsignedInteger('late_minutes')->default(0)->after('check_out_verification');
            $table->unsignedInteger('overtime_minutes')->default(0)->after('late_minutes');
            $table->unsignedInteger('approved_overtime_minutes')->default(0)->after('overtime_minutes');
            $table->string('overtime_status', 20)->default(OvertimeStatus::None->value)->after('approved_overtime_minutes');
            $table->foreignId('overtime_approved_by')->nullable()->after('overtime_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('overtime_approved_at')->nullable()->after('overtime_approved_by');
            $table->string('overtime_approval_note')->nullable()->after('overtime_approved_at');
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->index(['overtime_status', 'work_date'], 'attendance_records_overtime_index');
            $table->index(['source', 'work_date'], 'attendance_records_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropIndex('attendance_records_overtime_index');
            $table->dropIndex('attendance_records_source_index');
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shift_assignment_id');
            $table->dropConstrainedForeignId('overtime_approved_by');
            $table->dropColumn([
                'source',
                'check_in_latitude', 'check_in_longitude', 'check_in_accuracy_meters',
                'check_in_distance_meters', 'check_in_ip', 'check_in_user_agent', 'check_in_verification',
                'check_out_latitude', 'check_out_longitude', 'check_out_accuracy_meters',
                'check_out_distance_meters', 'check_out_ip', 'check_out_user_agent', 'check_out_verification',
                'late_minutes', 'overtime_minutes', 'approved_overtime_minutes',
                'overtime_status', 'overtime_approved_at', 'overtime_approval_note',
            ]);
        });
    }

    /** The same evidence block is captured for the way in and the way out. */
    private function addClockEvidence(Blueprint $table, string $prefix, string $after): void
    {
        $table->decimal($prefix.'_latitude', 10, 7)->nullable()->after($after);
        $table->decimal($prefix.'_longitude', 10, 7)->nullable()->after($prefix.'_latitude');
        $table->unsignedInteger($prefix.'_accuracy_meters')->nullable()->after($prefix.'_longitude');
        $table->unsignedInteger($prefix.'_distance_meters')->nullable()->after($prefix.'_accuracy_meters');
        $table->string($prefix.'_ip', 45)->nullable()->after($prefix.'_distance_meters');
        $table->string($prefix.'_user_agent')->nullable()->after($prefix.'_ip');
        $table->string($prefix.'_verification', 20)->nullable()->after($prefix.'_user_agent');
    }
};
