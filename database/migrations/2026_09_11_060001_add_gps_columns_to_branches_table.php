<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each shop physically is, and how forgiving its geofence may be.
 *
 * Coordinates are decimal, not float: seven decimal places resolve to roughly
 * a centimetre and the value round-trips exactly.
 *
 * GPS stays off until somebody has actually entered coordinates, so switching
 * the feature on is a deliberate act rather than a side effect of this upgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedSmallInteger('attendance_radius_meters')
                ->default(config('attendance.default_radius_meters', 100))
                ->after('longitude');
            $table->unsignedSmallInteger('attendance_accuracy_limit_meters')
                ->default(config('attendance.default_accuracy_limit_meters', 150))
                ->after('attendance_radius_meters');
            $table->boolean('gps_attendance_enabled')->default(false)->after('attendance_accuracy_limit_meters');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn([
                'latitude',
                'longitude',
                'attendance_radius_meters',
                'attendance_accuracy_limit_meters',
                'gps_attendance_enabled',
            ]);
        });
    }
};
