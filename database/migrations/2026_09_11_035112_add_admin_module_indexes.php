<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes backing the list filters and report aggregates of the admin modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->index(['status', 'starts_at'], 'appointments_status_starts_at_index');
            $table->index(['employee_id', 'starts_at', 'ends_at'], 'appointments_employee_slot_index');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['payment_method'], 'invoices_payment_method_index');
            $table->index(['created_at'], 'invoices_created_at_index');
            $table->index(['customer_phone'], 'invoices_customer_phone_index');
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->index(['employee_id', 'invoice_id'], 'invoice_items_employee_invoice_index');
            $table->index(['service_id'], 'invoice_items_service_index');
        });

        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->index(['occurred_at'], 'cash_transactions_occurred_at_index');
            $table->index(['category', 'occurred_at'], 'cash_transactions_category_occurred_at_index');
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->index(['type', 'occurred_at'], 'inventory_movements_type_occurred_at_index');
            $table->index(['supplier_id'], 'inventory_movements_supplier_index');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->index(['is_active', 'name'], 'products_active_name_index');
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->index(['work_date'], 'attendance_records_work_date_index');
            $table->index(['status', 'work_date'], 'attendance_records_status_work_date_index');
        });

        Schema::table('payrolls', function (Blueprint $table): void {
            $table->index(['status', 'period_start'], 'payrolls_status_period_index');
            $table->index(['paid_at'], 'payrolls_paid_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            $table->dropIndex('payrolls_status_period_index');
            $table->dropIndex('payrolls_paid_at_index');
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropIndex('attendance_records_work_date_index');
            $table->dropIndex('attendance_records_status_work_date_index');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_active_name_index');
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropIndex('inventory_movements_type_occurred_at_index');
            $table->dropIndex('inventory_movements_supplier_index');
        });

        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->dropIndex('cash_transactions_occurred_at_index');
            $table->dropIndex('cash_transactions_category_occurred_at_index');
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropIndex('invoice_items_employee_invoice_index');
            $table->dropIndex('invoice_items_service_index');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_payment_method_index');
            $table->dropIndex('invoices_created_at_index');
            $table->dropIndex('invoices_customer_phone_index');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex('appointments_status_starts_at_index');
            $table->dropIndex('appointments_employee_slot_index');
        });
    }
};
