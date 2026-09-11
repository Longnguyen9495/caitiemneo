<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Store the computed end of an appointment so slot overlaps can be detected with a
 * portable `starts_at < :end AND ends_at > :start` predicate instead of the
 * SQLite-only `datetime(starts_at, '+' || duration_minutes || ' minutes')` expression.
 *
 * The column stays nullable at the schema level: MySQL refuses a `TIMESTAMP NOT NULL`
 * column that has no default, and declaring it as `DATETIME` instead would mix two
 * different timezone semantics inside the same comparison. Appointment writes always
 * go through `SaveAppointmentAction`, which keeps `ends_at` in sync with
 * `starts_at + duration_minutes`, and legacy rows are backfilled below.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('appointments', 'ends_at')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->timestamp('ends_at')->nullable()->after('starts_at');
            });
        }

        $this->backfillEndsAt();
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn('ends_at');
        });
    }

    private function backfillEndsAt(): void
    {
        DB::table('appointments')
            ->whereNull('ends_at')
            ->select('id', 'starts_at', 'duration_minutes')
            ->orderBy('id')
            ->chunkById(500, function ($appointments): void {
                foreach ($appointments as $appointment) {
                    DB::table('appointments')
                        ->where('id', $appointment->id)
                        ->update([
                            'ends_at' => Carbon::parse($appointment->starts_at)
                                ->addMinutes((int) $appointment->duration_minutes),
                        ]);
                }
            });
    }
};
