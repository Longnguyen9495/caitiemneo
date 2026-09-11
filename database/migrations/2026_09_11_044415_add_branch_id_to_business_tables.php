<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attach every operational row to a branch.
 *
 * The column is added nullable, backfilled to the default branch, verified, and
 * only then tightened to NOT NULL. Tightening is skipped when a row could not be
 * backfilled, so the migration can never fail halfway and leave the schema in a
 * state the application cannot boot from.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const TABLES = [
        'appointments',
        'invoices',
        'cash_transactions',
        'inventory_movements',
        'attendance_records',
    ];

    public function up(): void
    {
        $branchId = DB::table('branches')->where('code', 'CN-01')->value('id');

        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->foreignId('branch_id')->nullable()->after('id')->constrained()->restrictOnDelete();
                });
            }

            DB::table($table)->whereNull('branch_id')->update(['branch_id' => $branchId]);

            if (DB::table($table)->whereNull('branch_id')->doesntExist()) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->foreignId('branch_id')->nullable(false)->change();
                });
            }
        }

        $this->addIndexes();
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropIndex($table.'_branch_scope_index');
                $blueprint->dropConstrainedForeignId('branch_id');
            });
        }
    }

    /** One composite index per table, matching how each list screen filters. */
    private function addIndexes(): void
    {
        Schema::table('appointments', fn (Blueprint $table) => $table->index(['branch_id', 'starts_at', 'status'], 'appointments_branch_scope_index'));
        Schema::table('invoices', fn (Blueprint $table) => $table->index(['branch_id', 'status', 'paid_at'], 'invoices_branch_scope_index'));
        Schema::table('cash_transactions', fn (Blueprint $table) => $table->index(['branch_id', 'type', 'occurred_at'], 'cash_transactions_branch_scope_index'));
        Schema::table('inventory_movements', fn (Blueprint $table) => $table->index(['branch_id', 'product_id', 'occurred_at'], 'inventory_movements_branch_scope_index'));
        Schema::table('attendance_records', fn (Blueprint $table) => $table->index(['branch_id', 'employee_id', 'work_date'], 'attendance_records_branch_scope_index'));
    }
};
