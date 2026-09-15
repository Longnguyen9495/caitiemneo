<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Accounts created after the compensation-table rollout but before the
     * employee form began writing dated profiles have rates on users only.
     * Restore their current profile, then repair only unfinalized invoice
     * snapshots that were calculated from the implicit zero-rate fallback.
     */
    public function up(): void
    {
        $today = now()->toDateString();

        DB::table('users')
            ->select('id', 'base_salary', 'shift_rate', 'commission_rate')
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($today): void {
                foreach ($users as $user) {
                    $hasProfile = DB::table('employee_compensation_profiles')
                        ->where('user_id', $user->id)
                        ->exists();

                    if ($hasProfile) {
                        continue;
                    }

                    DB::table('employee_compensation_profiles')->insert([
                        'user_id' => $user->id,
                        'branch_id' => null,
                        'base_salary' => $user->base_salary ?? 0,
                        'shift_rate' => $user->shift_rate ?? 0,
                        'regular_commission_rate' => $user->commission_rate ?? 0,
                        'overtime_commission_rate' => $user->commission_rate ?? 0,
                        'effective_from' => $today,
                        'effective_to' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        DB::table('invoices')
            ->join('users', 'users.id', '=', 'invoices.employee_id')
            ->where('invoices.status', 'draft')
            ->where('invoices.commission_rate', 0)
            ->where('invoices.commission_rate_source', 'compensation_profile')
            ->where('users.commission_rate', '>', 0)
            ->select('invoices.id', 'invoices.employee_id', 'users.commission_rate')
            ->orderBy('invoices.id')
            ->each(function (object $invoice): void {
                DB::table('invoices')
                    ->where('id', $invoice->id)
                    ->update([
                        'commission_rate' => $invoice->commission_rate,
                        'updated_at' => now(),
                    ]);

                DB::table('invoice_items')
                    ->where('invoice_id', $invoice->id)
                    ->where('commission_rate', 0)
                    ->where('commission_rate_source', 'compensation_profile')
                    ->update([
                        'employee_id' => $invoice->employee_id,
                        'commission_rate' => $invoice->commission_rate,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // The repair only enriches current configuration and editable drafts;
        // it must not remove rate history if this migration is rolled back.
    }
};
