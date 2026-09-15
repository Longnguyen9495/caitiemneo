<?php

namespace Database\Seeders;

use App\Actions\Payrolls\FinalizePayrollAction;
use App\Actions\Payrolls\PayPayrollAction;
use App\Actions\Payrolls\SavePayrollAction;
use App\Enums\PaymentMethod;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

/**
 * Bảng lương của kỳ đã khép: mỗi người một bảng, ba trạng thái khác nhau.
 *
 * Số tiền không được gán tay. {@see SavePayrollAction} chỉ nhận kỳ lương và
 * hai ô phụ cấp / khấu trừ, phần còn lại do bộ tính lương đọc từ chấm công và
 * từ hóa đơn đã thu — nên bảng lương demo là hệ quả của dữ liệu demo khác,
 * không phải một con số bịa ra rồi để đó.
 *
 * Vì thế seeder này phải chạy SAU chấm công và hóa đơn.
 */
class DemoPayrollSeeder extends Seeder
{
    public function __construct(
        private SavePayrollAction $savePayroll,
        private FinalizePayrollAction $finalizePayroll,
        private PayPayrollAction $payPayroll,
    ) {}

    public function run(): void
    {
        $actor = DemoData::actor();
        $periodStart = DemoData::lastPeriodStart();
        $periodEnd = DemoData::lastPeriodEnd();
        $counts = ['draft' => 0, 'finalized' => 0, 'paid' => 0];

        foreach (DemoData::staff()->values() as $index => $employee) {
            $payroll = $this->draft($employee, $actor, $index, $periodStart->toDateString(), $periodEnd->toDateString());

            if ($payroll === null) {
                continue;
            }

            // Cuối kỳ thật thì kế toán chốt gần hết rồi mới chi dần, nên để
            // lại vài bảng nháp và vài bảng đã chốt chưa trả.
            if ($index % 5 === 0) {
                $counts['draft']++;

                continue;
            }

            $this->finalizePayroll->handle($payroll, $actor);

            if ($index % 3 === 0) {
                $counts['finalized']++;

                continue;
            }

            $this->payPayroll->handle($payroll, $actor, PaymentMethod::Transfer);
            $counts['paid']++;
        }

        $this->command?->line(sprintf(
            '  Bảng lương %s: %d nháp, %d đã chốt, %d đã trả.',
            $periodStart->format('m/Y'), $counts['draft'], $counts['finalized'], $counts['paid'],
        ));
    }

    /**
     * Bảng lương nháp của một người, kèm phụ cấp hoặc khấu trừ nếu có.
     *
     * Trả về null khi người này đã có bảng lương phủ kỳ đó — chạy lại seeder
     * không được đẻ thêm bảng thứ hai cho cùng một tháng.
     */
    private function draft(User $employee, User $actor, int $index, string $periodStart, string $periodEnd): ?Payroll
    {
        $existing = Payroll::query()
            ->where('employee_id', $employee->getKey())
            ->where('period_start', $periodStart)
            ->first();

        if ($existing !== null) {
            return null;
        }

        try {
            return $this->savePayroll->create([
                'employee_id' => $employee->getKey(),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'adjustment' => $index % 4 === 0 ? 300_000 : 0,
                'deduction' => $index % 6 === 0 ? 150_000 : 0,
                'note' => $index % 4 === 0 ? 'Thưởng khách khen trên fanpage' : null,
            ], $actor);
        } catch (ValidationException) {
            return null;
        }
    }
}
