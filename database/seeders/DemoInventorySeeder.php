<?php

namespace Database\Seeders;

use App\Actions\Inventory\CompleteStockTransferAction;
use App\Actions\Inventory\RecordInventoryMovementAction;
use App\Enums\InventoryMovementType;
use App\Enums\StockTransferStatus;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Vật tư, nhà cung cấp và hai tháng xuất nhập kho của hai cơ sở.
 *
 * Mọi phiếu kho đều đi qua {@see RecordInventoryMovementAction} thay vì ghi
 * thẳng vào bảng, nên tồn kho demo tuân đúng luật của ứng dụng: không âm,
 * khoá theo cặp chi nhánh/vật tư, và có dòng nhật ký kèm tồn trước/sau.
 *
 * Số phiếu (`reference`) được sinh cố định để chạy lại seeder là bỏ qua phiếu
 * đã có chứ không cộng thêm tồn.
 */
class DemoInventorySeeder extends Seeder
{
    public function __construct(
        private RecordInventoryMovementAction $recordMovement,
        private CompleteStockTransferAction $completeTransfer,
    ) {}

    public function run(): void
    {
        $actor = DemoData::actor();
        $branches = DemoData::branches();

        $suppliers = $this->suppliers();
        $products = $this->products();

        $this->stockEveryBranch($branches, $products);
        $this->openingStock($branches, $products, $suppliers, $actor);
        $this->dailyUsage($branches, $products, $actor);
        $this->stockCount($branches->first(), $products->first(), $actor);
        $this->transfers($branches, $products, $actor);
    }

    /** @return Collection<int, Supplier> */
    private function suppliers(): Collection
    {
        $rows = [
            ['name' => 'Nail Supply Hà Nội', 'phone' => '0243 8765 121', 'email' => 'sales@nailsupplyhn.vn', 'address' => '128 Nguyễn Trãi, Thanh Xuân, Hà Nội'],
            ['name' => 'Công ty TNHH Mỹ phẩm Anh Thư', 'phone' => '0912 447 508', 'email' => 'kinhdoanh@anhthucosmetics.vn', 'address' => '55 Lê Duẩn, Hoàn Kiếm, Hà Nội'],
            ['name' => 'Gel Factory Việt Nam', 'phone' => '0287 305 992', 'email' => 'order@gelfactory.com.vn', 'address' => 'Lô B4 KCN Tân Bình, TP. Hồ Chí Minh'],
            ['name' => 'Phụ kiện Nail Minh Châu', 'phone' => '0968 210 334', 'email' => 'minhchau.nail@gmail.com', 'address' => '19 ngõ 72 Quan Nhân, Cầu Giấy, Hà Nội'],
        ];

        return collect($rows)->map(fn (array $row): Supplier => Supplier::query()->firstOrCreate(
            ['name' => $row['name']],
            $row + ['is_active' => true],
        ));
    }

    /** @return Collection<int, Product> */
    private function products(): Collection
    {
        $rows = [
            ['sku' => 'VT-GEL-001', 'name' => 'Sơn gel hồng nude', 'unit' => 'lọ', 'cost_price' => 85_000, 'minimum_stock' => 6],
            ['sku' => 'VT-GEL-002', 'name' => 'Sơn gel đỏ đô', 'unit' => 'lọ', 'cost_price' => 85_000, 'minimum_stock' => 6],
            ['sku' => 'VT-GEL-003', 'name' => 'Sơn gel mắt mèo', 'unit' => 'lọ', 'cost_price' => 120_000, 'minimum_stock' => 4],
            ['sku' => 'VT-BASE-001', 'name' => 'Base gel dưỡng móng', 'unit' => 'lọ', 'cost_price' => 160_000, 'minimum_stock' => 5],
            ['sku' => 'VT-TOP-001', 'name' => 'Top gel bóng không lau', 'unit' => 'lọ', 'cost_price' => 175_000, 'minimum_stock' => 5],
            ['sku' => 'VT-BOT-001', 'name' => 'Bột đắp móng trong', 'unit' => 'hộp', 'cost_price' => 340_000, 'minimum_stock' => 3],
            ['sku' => 'VT-PHA-001', 'name' => 'Dung dịch phá gel', 'unit' => 'chai', 'cost_price' => 65_000, 'minimum_stock' => 8],
            ['sku' => 'VT-CON-001', 'name' => 'Cồn sát khuẩn 70 độ', 'unit' => 'chai', 'cost_price' => 32_000, 'minimum_stock' => 10],
            ['sku' => 'VT-DUA-001', 'name' => 'Dũa móng 100/180', 'unit' => 'cái', 'cost_price' => 7_000, 'minimum_stock' => 40],
            ['sku' => 'VT-DA-001', 'name' => 'Đá trang trí pha lê', 'unit' => 'vỉ', 'cost_price' => 45_000, 'minimum_stock' => 10],
        ];

        return collect($rows)->map(fn (array $row): Product => Product::query()->firstOrCreate(
            ['sku' => $row['sku']],
            $row + ['is_active' => true],
        ));
    }

    /**
     * Cơ sở nào cũng bán đủ danh mục, nên cặp chi nhánh/vật tư được mở sẵn.
     *
     * @param  Collection<int, Branch>  $branches
     * @param  Collection<int, Product>  $products
     */
    private function stockEveryBranch(Collection $branches, Collection $products): void
    {
        foreach ($branches as $branch) {
            foreach ($products as $product) {
                BranchProduct::query()->firstOrCreate(
                    ['branch_id' => $branch->getKey(), 'product_id' => $product->getKey()],
                    ['minimum_stock' => $product->minimum_stock, 'is_active' => true],
                );
            }
        }
    }

    /**
     * Phiếu nhập đầu kỳ: mỗi cơ sở lấy một đợt hàng từ nhà cung cấp.
     *
     * @param  Collection<int, Branch>  $branches
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Supplier>  $suppliers
     */
    private function openingStock(
        Collection $branches,
        Collection $products,
        Collection $suppliers,
        User $actor,
    ): void {
        foreach ($branches as $branchIndex => $branch) {
            foreach ($products as $productIndex => $product) {
                $supplier = $suppliers[$productIndex % $suppliers->count()];
                $occurredAt = DemoData::start()->copy()->addDays($branchIndex)->setTime(8, 30);

                $this->movement([
                    'branch_id' => $branch->getKey(),
                    'product_id' => $product->getKey(),
                    'supplier_id' => $supplier->getKey(),
                    'type' => InventoryMovementType::In->value,
                    'quantity' => $product->minimum_stock * 6,
                    'unit_cost' => $product->cost_price,
                    'reference' => sprintf('NK-%s-%s', $branch->code, $product->sku),
                    'note' => 'Nhập hàng đầu kỳ từ '.$supplier->name,
                    'occurred_at' => $occurredAt,
                ], $actor);

                // Một lần nhập bổ sung giữa kỳ cho các vật tư tiêu hao nhanh.
                if ($product->minimum_stock < 8) {
                    continue;
                }

                $this->movement([
                    'branch_id' => $branch->getKey(),
                    'product_id' => $product->getKey(),
                    'supplier_id' => $supplier->getKey(),
                    'type' => InventoryMovementType::In->value,
                    'quantity' => $product->minimum_stock * 3,
                    'unit_cost' => $product->cost_price,
                    'reference' => sprintf('NK2-%s-%s', $branch->code, $product->sku),
                    'note' => 'Nhập bổ sung giữa kỳ',
                    'occurred_at' => $occurredAt->copy()->addDays(21),
                ], $actor);
            }
        }
    }

    /**
     * Vật tư đi ra theo nhịp làm nghề: vài lần mỗi tháng, mỗi lần một ít.
     *
     * @param  Collection<int, Branch>  $branches
     * @param  Collection<int, Product>  $products
     */
    private function dailyUsage(
        Collection $branches,
        Collection $products,
        User $actor,
    ): void {
        foreach ($branches as $branch) {
            foreach ($products as $product) {
                foreach (range(1, 4) as $round) {
                    $this->movement([
                        'branch_id' => $branch->getKey(),
                        'product_id' => $product->getKey(),
                        'type' => InventoryMovementType::Out->value,
                        'quantity' => max(1, (int) round($product->minimum_stock / 2)),
                        'unit_cost' => $product->cost_price,
                        'reference' => sprintf('XK-%s-%s-%d', $branch->code, $product->sku, $round),
                        'note' => 'Xuất dùng tại quầy',
                        'occurred_at' => DemoData::start()->copy()->addDays($round * 11)->setTime(19, 0),
                    ], $actor);
                }
            }
        }
    }

    /** Một phiếu kiểm kê để màn hình kho có cả loại điều chỉnh. */
    private function stockCount(Branch $branch, Product $product, User $actor): void
    {
        $counted = RecordInventoryMovementAction::branchStockMinor($branch->getKey(), $product->getKey()) / 100 - 2;

        $this->movement([
            'branch_id' => $branch->getKey(),
            'product_id' => $product->getKey(),
            'type' => InventoryMovementType::Adjustment->value,
            'adjustment_mode' => 'absolute',
            'quantity' => max($counted, 0),
            'unit_cost' => $product->cost_price,
            'reference' => sprintf('KK-%s-%s', $branch->code, $product->sku),
            'note' => 'Kiểm kê cuối tháng, lệch 2 '.$product->unit.' so với hệ thống',
            'occurred_at' => DemoData::start()->copy()->endOfMonth()->setTime(21, 30),
        ], $actor);
    }

    /**
     * Một phiếu chuyển kho đã hoàn tất và một phiếu còn nháp.
     *
     * @param  Collection<int, Branch>  $branches
     * @param  Collection<int, Product>  $products
     */
    private function transfers(
        Collection $branches,
        Collection $products,
        User $actor,
    ): void {
        if ($branches->count() < 2) {
            return;
        }

        [$source, $destination] = [$branches[0], $branches[1]];

        $completed = $this->transfer(
            'CK-DEMO-0001',
            $source,
            $destination,
            $products->take(3),
            $actor,
            'Chuyển hàng hỗ trợ cơ sở mới khai trương',
            DemoData::start()->copy()->addDays(9)->setTime(9, 0),
        );

        if ($completed->status === StockTransferStatus::Draft) {
            $this->completeTransfer->handle($completed, $actor);
        }

        $this->transfer(
            'CK-DEMO-0002',
            $destination,
            $source,
            $products->slice(5, 2),
            $actor,
            'Trả lại bột đắp chưa dùng đến, chờ quản lý duyệt',
            DemoData::today()->copy()->subDays(2)->setTime(18, 0),
        );
    }

    /** @param Collection<int, Product> $products */
    private function transfer(
        string $number,
        Branch $source,
        Branch $destination,
        Collection $products,
        User $actor,
        string $note,
        CarbonInterface $createdAt,
    ): StockTransfer {
        $transfer = StockTransfer::query()->firstOrCreate(
            ['number' => $number],
            [
                'source_branch_id' => $source->getKey(),
                'destination_branch_id' => $destination->getKey(),
                'status' => StockTransferStatus::Draft,
                'created_by' => $actor->getKey(),
                'note' => $note,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ],
        );

        foreach ($products as $product) {
            $transfer->items()->firstOrCreate(
                ['product_id' => $product->getKey()],
                ['quantity' => 4, 'unit_cost' => $product->cost_price],
            );
        }

        return $transfer->refresh();
    }

    /**
     * Ghi một phiếu kho, bỏ qua nếu số phiếu đó đã tồn tại.
     *
     * @param  array<string, mixed>  $data
     */
    private function movement(array $data, User $actor): void
    {
        if (InventoryMovement::query()->where('reference', $data['reference'])->exists()) {
            return;
        }

        $this->recordMovement->handle($data, $actor);
    }
}
