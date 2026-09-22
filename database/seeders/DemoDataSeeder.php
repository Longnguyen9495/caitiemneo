<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Một tiệm đang chạy thật: hai tháng khách, hóa đơn, kho, công và lương.
 *
 *     php artisan db:seed --class=DemoDataSeeder
 *
 * Cố ý KHÔNG nằm trong {@see DatabaseSeeder}. Seeder đó chỉ dựng phần cấu hình
 * mà tiệm thật cũng cần (chi nhánh, bảng giá, ca, nhân sự, chính sách lương);
 * còn đây là khách hàng và tiền bịa ra, thứ không bao giờ được rơi vào cơ sở
 * dữ liệu đang chạy thật.
 *
 * Thứ tự các bước là ràng buộc chứ không phải sở thích: hóa đơn cần thợ có ca,
 * còn bảng lương cần cả chấm công lẫn hóa đơn đã thu mới ra được số.
 *
 * Faker chạy từ một hạt giống cố định và mọi bản ghi đều khớp theo khoá tự
 * nhiên, nên chạy lần thứ hai là bồi vào chỗ còn thiếu chứ không nhân đôi.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoDataSeeder không chạy trên production.');

            return;
        }

        fake()->seed(20260915);

        $this->call([
            DatabaseSeeder::class,
            DemoInventorySeeder::class,
            DemoWorkforceSeeder::class,
            DemoSalesSeeder::class,
            DemoCashSeeder::class,
            DemoPayrollSeeder::class,
            DemoFeedbackSeeder::class,
        ]);

        // Hàng đợi cảnh báo là kết quả của dữ liệu ở trên chứ không phải một
        // bảng để ghi tay, nên nó được dựng bằng chính bộ dò của ứng dụng.
        Artisan::call('risk:detect', [
            '--days' => (int) ceil(DemoData::start()->diffInDays(DemoData::today())) + 1,
        ], $this->command?->getOutput());
    }
}
