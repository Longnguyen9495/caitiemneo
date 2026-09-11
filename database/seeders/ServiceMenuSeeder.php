<?php

namespace Database\Seeders;

use App\Enums\ServiceCategory;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Bảng giá thật của Cái Tiệm Neo, chép nguyên từ menu giấy.
 *
 * Quy ước cột giá:
 *   - `price`  : mức điền sẵn khi lên hóa đơn.
 *   - `min/max`: khoảng thợ được chốt tùy độ khó. Null nghĩa là giá cố định.
 *
 * THỜI LƯỢNG (`duration_minutes`) LÀ SỐ ƯỚC LƯỢNG do chưa có số liệu thật.
 * Nó chỉ dùng để xếp lịch hẹn, không ảnh hưởng tới tiền. Hãy chỉnh lại ở màn
 * hình Chi nhánh → Danh mục khi biết thời gian thực tế.
 *
 * Hoa hồng cố tình để trống: chưa thống nhất mức theo dịch vụ, nên hệ thống
 * dùng tỷ lệ của từng nhân viên như hiện tại.
 */
class ServiceMenuSeeder extends Seeder
{
    /**
     * Mỗi dòng: [tên, giá mặc định, giá sàn, giá trần, thời lượng ước lượng].
     * Giá sàn/trần null = giá cố định.
     *
     * @return array<string, array<int, array{0: string, 1: int, 2: int|null, 3: int|null, 4: int}>>
     */
    private function menu(): array
    {
        return [
            ServiceCategory::BasicNail->value => [
                ['Sơn gel (free sửa và cứng móng)', 130_000, null, null, 60],
                ['Phá gel (móng thật, móng giả)', 20_000, 20_000, 50_000, 20],
                ['Sửa móng', 40_000, null, null, 20],
                ['Sơn nhũ', 150_000, null, null, 70],
                ['Sơn thạch', 140_000, null, null, 70],
                ['Sơn mắt mèo (không nền)', 180_000, null, null, 75],
                ['Sơn mắt mèo (có nền thạch)', 220_000, null, null, 90],
                ['Tráng gương (không nền)', 180_000, null, null, 75],
                ['Tráng gương (có nền)', 220_000, null, null, 90],
                ['Dưỡng cứng móng', 80_000, null, null, 30],
            ],
            ServiceCategory::Extension->value => [
                ['Úp móng (base sát chân)', 100_000, null, null, 75],
                ['Úp base tạo cầu (nuôi móng thật)', 120_000, null, null, 90],
                ['Nối móng đắp gel', 220_000, null, null, 120],
                ['Fill móng up/gel', 80_000, 80_000, 150_000, 90],
            ],
            ServiceCategory::Decoration->value => [
                ['Mắt mèo, ngũ cốc, trứng cút', 5_000, 5_000, 10_000, 5],
                ['Tráng gương', 15_000, null, null, 5],
                ['Ombre', 10_000, 10_000, 15_000, 5],
                ['French đầu móng', 10_000, null, null, 5],
                ['Loang, vân đá tả thực', 15_000, 15_000, 50_000, 10],
                ['Ẩn nhũ, xà cừ, thủy tinh, đá rắc', 10_000, 10_000, 40_000, 8],
                ['Sticker', 5_000, 5_000, 10_000, 3],
                ['Vẽ móng (tùy độ dễ, khó)', 5_000, 5_000, 30_000, 10],
                ['Vẽ hoạt hình (tùy hình)', 50_000, 50_000, 100_000, 20],
                ['Gel nổi, gel nặn', 10_000, 10_000, 50_000, 15],
                ['Charm đá', 10_000, 10_000, 50_000, 5],
                ['Đá nhỏ, phụ kiện', 5_000, 5_000, 30_000, 5],
            ],
        ];
    }

    public function run(): void
    {
        $branches = Branch::query()->orderBy('id')->get();

        foreach ($this->menu() as $categoryValue => $rows) {
            $category = ServiceCategory::from($categoryValue);

            foreach (array_values($rows) as $order => [$name, $price, $min, $max, $minutes]) {
                $service = $this->service($category, $name, $price, $min, $max, $order);

                foreach ($branches as $branch) {
                    $this->offer($branch, $service, $minutes);
                }
            }
        }
    }

    private function service(ServiceCategory $category, string $name, int $price, ?int $min, ?int $max, int $order): Service
    {
        // Khớp theo tên trong cùng nhóm: "Tráng gương" xuất hiện ở cả nhóm
        // Nail cơ bản (theo bộ, 180k) lẫn nhóm Trang trí (theo ngón, 15k),
        // nên tên một mình không đủ để phân biệt.
        $service = Service::query()->firstOrNew([
            'name' => $name,
            'category' => $category->value,
        ]);

        if ($service->exists) {
            $this->command?->line(sprintf('  Bỏ qua "%s": đã có trong danh mục.', $name));

            return $service;
        }

        $service->fill([
            'unit' => $category->defaultUnit(),
            'price' => $price,
            'price_min' => $min,
            'price_max' => $max,
            'display_order' => $order,
            'is_active' => true,
        ])->save();

        return $service;
    }

    private function offer(Branch $branch, Service $service, int $minutes): void
    {
        $existing = BranchService::query()
            ->where('branch_id', $branch->getKey())
            ->where('service_id', $service->getKey())
            ->first();

        if ($existing !== null) {
            return;
        }

        BranchService::query()->create([
            'branch_id' => $branch->getKey(),
            'service_id' => $service->getKey(),
            'price' => $service->price,
            'price_min' => $service->price_min,
            'price_max' => $service->price_max,
            'duration_minutes' => $minutes,
            // Để trống: hệ thống rơi về tỷ lệ hoa hồng của từng nhân viên.
            'commission_rate' => null,
            'overtime_commission_rate' => null,
            'is_active' => true,
        ]);
    }
}
