<?php

namespace App\Console\Commands;

use App\Services\Risk\RiskDetector;
use Illuminate\Console\Command;

/**
 * Quét lại nhật ký để dựng hàng đợi cảnh báo.
 *
 * Màn hình cảnh báo cũng tự quét khi mở, nên lệnh này chỉ là đường chạy nền
 * cho tiệm đã bật scheduler; cả hai dùng chung một bộ quy tắc và đều idempotent.
 */
class DetectRiskFlagsCommand extends Command
{
    protected $signature = 'risk:detect {--days=7 : Số ngày quét ngược}';

    protected $description = 'Dò các thao tác bất thường và dựng hàng đợi cảnh báo';

    public function handle(RiskDetector $detector): int
    {
        $days = max(1, (int) $this->option('days'));
        $raised = $detector->sweep(now()->subDays($days));

        $this->info($raised > 0
            ? "Đã thêm {$raised} cảnh báo mới trong {$days} ngày gần nhất."
            : "Không có cảnh báo mới trong {$days} ngày gần nhất.");

        return self::SUCCESS;
    }
}
