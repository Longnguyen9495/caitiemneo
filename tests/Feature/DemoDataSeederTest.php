<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AttendanceRecord;
use App\Models\CashTransaction;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\ShiftAssignment;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bộ dữ liệu demo là công cụ của lập trình viên, nhưng nó ghi vào đúng những
 * bảng mà ứng dụng thật dùng, nên phải giữ được hai tính chất.
 *
 * Một: mỗi màn hình quản trị đều có cái để hiển thị. Hai: chạy lại là bồi vào
 * chỗ thiếu chứ không nhân đôi — tính chất này từng hỏng vì một chỗ chọn dịch
 * vụ bằng `random()` nằm ngoài hạt giống của faker, khiến giờ hẹn lần sau
 * lệch đi và seeder không nhận ra lịch hẹn nó đã tạo.
 */
class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_every_admin_screen_and_stays_idempotent(): void
    {
        $this->seed(DemoDataSeeder::class);

        $first = $this->counts();

        foreach ($first as $table => $count) {
            $this->assertGreaterThan(0, $count, "Bảng {$table} không có dữ liệu demo nào.");
        }

        $this->seed(DemoDataSeeder::class);

        $this->assertSame($first, $this->counts(), 'Chạy seeder lần hai đã tạo thêm dữ liệu trùng.');
    }

    public function test_paid_invoices_feed_the_cash_book_and_the_payroll(): void
    {
        $this->seed(DemoDataSeeder::class);

        $paid = Invoice::query()->where('status', 'paid')->get();

        $this->assertGreaterThan(0, $paid->count());

        // Mỗi hóa đơn đã thu phải có đúng một dòng thu trong sổ quỹ; nếu không
        // thì doanh thu demo đang là con số ghi tay chứ không phải tiền thật.
        $this->assertSame(
            $paid->count(),
            CashTransaction::query()->whereIn('invoice_id', $paid->modelKeys())->count(),
        );

        $this->assertGreaterThan(
            0,
            Payroll::query()->where('commission_pay', '>', 0)->count(),
            'Không bảng lương nào ăn theo hoa hồng của hóa đơn đã thu.',
        );
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'appointments' => Appointment::query()->count(),
            'invoices' => Invoice::query()->count(),
            'cash_transactions' => CashTransaction::query()->count(),
            'inventory_movements' => InventoryMovement::query()->count(),
            'shift_assignments' => ShiftAssignment::query()->count(),
            'attendance_records' => AttendanceRecord::query()->count(),
            'payrolls' => Payroll::query()->count(),
        ];
    }
}
