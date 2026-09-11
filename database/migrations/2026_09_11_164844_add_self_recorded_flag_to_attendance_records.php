<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mark the shifts somebody wrote for themselves.
 *
 * A manager writing their own hours is now blocked outright, but the owner
 * cannot be: a one-owner shop would be locked out of its own records. They keep
 * the ability and the row is flagged instead, so the review queue can put those
 * entries in front of a human rather than letting them pass unnoticed.
 *
 * Self-service clock events are not self-dealing — that is the whole point of
 * the GPS flow — so the flag is only set for hand-written entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->boolean('is_self_recorded')->default(false)->after('source');
            $table->index(['branch_id', 'is_self_recorded'], 'attendance_self_recorded_index');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropIndex('attendance_self_recorded_index');
            $table->dropColumn('is_self_recorded');
        });
    }

    /**
     * Existing hand-written rows whose author is the employee themselves.
     *
     * The record itself never stored who wrote it — that only ever lived in the
     * audit trail — so history is reconstructed from the manual-create entries
     * there. Marking the past honestly matters: otherwise the review queue is
     * silently blind to everything written before today.
     */
    private function backfill(): void
    {
        if (! Schema::hasTable('attendance_audit_logs')) {
            return;
        }

        $selfRecordedIds = DB::table('attendance_audit_logs')
            ->join('attendance_records', 'attendance_records.id', '=', 'attendance_audit_logs.attendance_record_id')
            ->whereIn('attendance_audit_logs.action', ['manual_create', 'manual_update'])
            ->whereColumn('attendance_audit_logs.actor_id', 'attendance_records.employee_id')
            ->pluck('attendance_records.id')
            ->unique()
            ->all();

        if ($selfRecordedIds === []) {
            return;
        }

        DB::table('attendance_records')
            ->whereIn('id', $selfRecordedIds)
            ->update(['is_self_recorded' => true]);
    }
};
