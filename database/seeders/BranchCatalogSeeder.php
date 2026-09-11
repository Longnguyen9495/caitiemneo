<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\BranchService;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Service menu and stock thresholds for each shop.
 *
 * The catalogues are shared, but Thảo Điền charges more and keeps deeper stock,
 * which is exactly the difference the branch overlay tables exist to express.
 */
class BranchCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $branchA = Branch::query()->where('code', 'CN-01')->firstOrFail();
        $branchB = Branch::query()->where('code', 'CN-02')->firstOrFail();

        $services = [
            ['name' => 'Sơn gel cơ bản', 'price' => 250000, 'duration' => 60],
            ['name' => 'Đắp bột nghệ thuật', 'price' => 450000, 'duration' => 120],
            ['name' => 'Chăm sóc móng tay', 'price' => 180000, 'duration' => 45],
        ];

        foreach ($services as $row) {
            $service = Service::query()->updateOrCreate(
                ['name' => $row['name']],
                ['price' => $row['price'], 'is_active' => true],
            );

            $this->offerService($branchA, $service, $row['price'], $row['duration'], 15, 20);

            // The Thảo Điền menu runs 20% dearer and pays a slightly higher rate.
            $this->offerService($branchB, $service, (int) round($row['price'] * 1.2, -3), $row['duration'] + 15, 18, 22);
        }

        $products = [
            ['name' => 'Sơn gel màu đỏ', 'sku' => 'SG-RED', 'unit' => 'chai', 'cost' => 85000],
            ['name' => 'Nước rửa dụng cụ', 'sku' => 'NR-01', 'unit' => 'lít', 'cost' => 120000],
            ['name' => 'Bột đắp móng', 'sku' => 'BD-01', 'unit' => 'hộp', 'cost' => 260000],
        ];

        foreach ($products as $row) {
            $product = Product::query()->updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'name' => $row['name'],
                    'unit' => $row['unit'],
                    'cost_price' => $row['cost'],
                    'minimum_stock' => 5,
                    'is_active' => true,
                ],
            );

            $this->stockProduct($branchA, $product, 5);
            $this->stockProduct($branchB, $product, 8);
        }
    }

    private function offerService(Branch $branch, Service $service, int $price, int $duration, int $rate, int $overtimeRate): void
    {
        BranchService::query()->updateOrCreate(
            ['branch_id' => $branch->getKey(), 'service_id' => $service->getKey()],
            [
                'price' => $price,
                'duration_minutes' => $duration,
                'commission_rate' => $rate,
                'overtime_commission_rate' => $overtimeRate,
                'is_active' => true,
            ],
        );
    }

    private function stockProduct(Branch $branch, Product $product, int $minimumStock): void
    {
        BranchProduct::query()->updateOrCreate(
            ['branch_id' => $branch->getKey(), 'product_id' => $product->getKey()],
            ['minimum_stock' => $minimumStock, 'is_active' => true],
        );
    }
}
