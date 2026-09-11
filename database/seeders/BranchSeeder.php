<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

/**
 * Các cơ sở của tiệm.
 *
 * Seeder này CỐ Ý không ghi đè chi nhánh đã tồn tại. Bản trước dùng
 * `updateOrCreate` nên một lần chạy `db:seed` đã xoá mất tên chi nhánh mà
 * quản lý tự đặt trong giao diện. Giờ nó chỉ ghi trong hai trường hợp:
 * chi nhánh chưa có, hoặc hàng đó vẫn nguyên là bản nháp do migration tạo ra.
 */
class BranchSeeder extends Seeder
{
    /**
     * Tên do migration `create_branch_tables` đặt cho chi nhánh mặc định.
     *
     * Một hàng còn mang đúng tên này và chưa có địa chỉ nghĩa là chưa ai đụng
     * vào, nên được phép ghi đè.
     */
    private const MIGRATION_PLACEHOLDER = 'Chi nhánh chính';

    public function run(): void
    {
        $this->seed('CN-01', [
            'name' => 'Cái Tiệm Neo Thái Hà',
            'address' => '47 ngõ 131 Thái Hà, Đống Đa, Hà Nội',
            'phone' => '0826881094',
            // Toạ độ thật của cửa hàng, dùng làm tâm vùng chấm công GPS.
            'latitude' => '21.0120839',
            'longitude' => '105.8184040',
        ]);

        // Cơ sở thứ hai: mới có tên, chưa có địa chỉ và toạ độ thật. Phải nhập
        // ở màn hình Chi nhánh trước khi bật chấm công GPS cho cơ sở này.
        $this->seed('CN-02', [
            'name' => 'Cái Tiệm Neo Nguyễn Đức Cảnh',
        ]);
    }

    /** @param  array<string, string>  $attributes */
    private function seed(string $code, array $attributes): void
    {
        $branch = Branch::query()->where('code', $code)->first();

        if ($branch === null) {
            Branch::query()->create($attributes + ['code' => $code, 'is_active' => true]);

            return;
        }

        if ($branch->name === self::MIGRATION_PLACEHOLDER && $branch->address === null) {
            $branch->fill($attributes)->save();

            return;
        }

        $this->command?->line(sprintf('  Bỏ qua %s: chi nhánh đã có dữ liệu riêng.', $code));
    }
}
