<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('employee_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            $table->decimal('commission_rate', 5, 2)->default(0)->after('total');
            $table->string('commission_rate_source')->nullable()->after('commission_rate');
        });

        // Historical invoices that already have exactly one credited employee can
        // be safely promoted to the invoice-level relationship. Multi-employee
        // legacy invoices deliberately remain unassigned for audit safety.
        DB::table('invoices')->orderBy('id')->chunkById(500, function ($invoices): void {
            foreach ($invoices as $invoice) {
                $employeeIds = DB::table('invoice_items')
                    ->where('invoice_id', $invoice->id)
                    ->whereNotNull('employee_id')
                    ->distinct()
                    ->pluck('employee_id');

                if ($employeeIds->count() !== 1) {
                    continue;
                }

                $rate = DB::table('invoice_items')
                    ->where('invoice_id', $invoice->id)
                    ->value('commission_rate') ?? 0;

                DB::table('invoices')->where('id', $invoice->id)->update([
                    'employee_id' => $employeeIds->first(),
                    'commission_rate' => $rate,
                    'commission_rate_source' => 'legacy_invoice_items',
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn(['commission_rate', 'commission_rate_source']);
        });
    }
};
