<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('employee')->index();
            $table->string('phone', 30)->nullable();
            $table->decimal('base_salary', 14, 2)->default(0);
            $table->decimal('shift_rate', 14, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->boolean('can_manage_appointments')->default(false);
            $table->boolean('can_create_invoices')->default(false);
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'phone',
                'base_salary',
                'shift_rate',
                'commission_rate',
                'can_manage_appointments',
                'can_create_invoices',
                'is_active',
            ]);
        });
    }
};
