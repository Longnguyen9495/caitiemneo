<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('customer_email')->nullable()->after('customer_phone');
            $table->index('customer_email');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['customer_email']);
            $table->dropColumn('customer_email');
        });
    }
};
