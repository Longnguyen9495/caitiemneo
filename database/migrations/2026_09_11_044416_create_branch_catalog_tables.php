<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-branch overlays on the shared catalogues.
 *
 * `services` and `products` stay global so a service means the same thing in
 * both shops; price, duration, commission and stock thresholds are what differ,
 * and those live here. Existing catalogue rows are copied onto the default
 * branch so nothing changes behaviour on the day of the upgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 14, 2);
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->decimal('overtime_commission_rate', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'service_id']);
            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('branch_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('minimum_stock', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
            $table->index(['branch_id', 'is_active']);
        });

        $this->backfillDefaultBranch();
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_products');
        Schema::dropIfExists('branch_services');
    }

    private function backfillDefaultBranch(): void
    {
        $branchId = DB::table('branches')->where('code', 'CN-01')->value('id');

        if ($branchId === null) {
            return;
        }

        DB::table('services')->orderBy('id')->chunkById(500, function ($services) use ($branchId): void {
            foreach ($services as $service) {
                DB::table('branch_services')->insertOrIgnore([
                    'branch_id' => $branchId,
                    'service_id' => $service->id,
                    'price' => $service->price,
                    'duration_minutes' => 60,
                    'commission_rate' => null,
                    'overtime_commission_rate' => null,
                    'is_active' => $service->is_active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        DB::table('products')->orderBy('id')->chunkById(500, function ($products) use ($branchId): void {
            foreach ($products as $product) {
                DB::table('branch_products')->insertOrIgnore([
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'minimum_stock' => $product->minimum_stock,
                    'is_active' => $product->is_active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
};
