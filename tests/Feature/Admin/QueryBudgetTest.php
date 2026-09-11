<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\RiskFlag;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A budget on how many queries a list screen may run.
 *
 * The point is not the exact number; it is that the number stays flat as rows
 * are added. A page that fires one query per row is fine on the fifteen rows a
 * developer tests with and unusable on the two thousand a real branch has by
 * the end of a year — and it degrades slowly, so nobody can say when it broke.
 *
 * Each test seeds enough rows that an N+1 would be obvious, then asserts a
 * ceiling comfortably above the correct count so ordinary changes do not fail
 * the build for no reason.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->owner = User::factory()->owner()->atBranch($this->branch)->create();
    }

    public function test_the_attendance_list_stays_flat(): void
    {
        $employees = User::factory()->count(6)->employee()->atBranch($this->branch)->create();

        foreach ($employees as $index => $employee) {
            // Unique theo (nhân viên, ngày, tên ca) nên mỗi dòng phải khác ngày.
            foreach (range(1, 3) as $day) {
                AttendanceRecord::factory()->create([
                    'branch_id' => $this->branch->getKey(),
                    'employee_id' => $employee->getKey(),
                    'work_date' => now()->subDays($day)->toDateString(),
                    'shift_name' => 'Ca '.$index,
                ]);
            }
        }

        $this->assertQueryBudget(route('admin.attendance.index'), 20, 'Danh sách chấm công');
    }

    public function test_the_attendance_review_queue_stays_flat(): void
    {
        $employees = User::factory()->count(5)->employee()->atBranch($this->branch)->create();

        foreach ($employees as $index => $employee) {
            foreach (range(1, 2) as $day) {
                AttendanceRecord::factory()->create([
                    'branch_id' => $this->branch->getKey(),
                    'employee_id' => $employee->getKey(),
                    'work_date' => now()->subDays($day)->toDateString(),
                    'shift_name' => 'Ca '.$index,
                ]);
            }
        }

        $this->assertQueryBudget(route('admin.attendance.review'), 25, 'Hàng đợi duyệt chấm công');
    }

    public function test_the_reports_page_stays_flat(): void
    {
        $employees = User::factory()->count(5)->employee()->atBranch($this->branch)->create();

        foreach ($employees as $employee) {
            $invoice = Invoice::factory()->create([
                'branch_id' => $this->branch->getKey(),
                'created_by' => $this->owner->getKey(),
                'status' => InvoiceStatus::Paid,
                'total' => '200000.00',
                'paid_at' => now(),
            ]);

            $invoice->items()->create([
                'name' => 'Son gel',
                'quantity' => 1,
                'unit_price' => 200000,
                'line_total' => 200000,
                'commission_rate' => 10,
                'commission_amount' => 20000,
                'employee_id' => $employee->getKey(),
            ]);
        }

        // Trang báo cáo là một bảng điều khiển rộng với nhiều phép tổng hợp
        // độc lập, không phải danh sách. Đã đo với gấp ba số dòng và vẫn đúng
        // 30 truy vấn — con số phẳng theo dữ liệu, nên ngưỡng đặt trên mức đó
        // để bắt được N+1 chứ không phải để phạt bề rộng sẵn có.
        $this->assertQueryBudget(route('admin.reports.index'), 35, 'Trang báo cáo');
    }

    public function test_the_stock_transfer_list_stays_flat(): void
    {
        $destination = Branch::factory()->create();

        for ($index = 0; $index < 6; $index++) {
            $transfer = StockTransfer::factory()->create([
                'source_branch_id' => $this->branch->getKey(),
                'destination_branch_id' => $destination->getKey(),
                'created_by' => $this->owner->getKey(),
            ]);

            // Unique theo (phiếu, vật tư) nên mỗi dòng phải là vật tư khác.
            foreach (range(1, 2) as $line) {
                StockTransferItem::factory()->create([
                    'stock_transfer_id' => $transfer->getKey(),
                    'product_id' => Product::factory()->create()->getKey(),
                ]);
            }
        }

        $this->assertQueryBudget(route('admin.stock-transfers.index'), 20, 'Danh sách chuyển kho');
    }

    public function test_the_risk_queue_stays_flat(): void
    {
        for ($index = 0; $index < 10; $index++) {
            RiskFlag::query()->create([
                'rule' => 'invoice_cancellations',
                'severity' => 'high',
                'subject_type' => 'actor_day',
                'subject_id' => $index,
                'branch_id' => $this->branch->getKey(),
                'actor_id' => User::factory()->employee()->atBranch($this->branch)->create()->getKey(),
                'actor_name' => 'Nguoi thao tac '.$index,
                'summary' => 'Canh bao thu nghiem '.$index,
                'detected_at' => now(),
            ]);
        }

        $this->assertQueryBudget(route('admin.risk-flags.index'), 30, 'Hàng đợi cảnh báo');
    }

    /**
     * Load a page and assert it stayed under the budget.
     *
     * The branch is pinned because the owner can see every shop: without it
     * BranchContext picks whichever branch happens to be first and the page
     * renders an empty list, which would pass any budget at all.
     */
    private function assertQueryBudget(string $url, int $budget, string $label): void
    {
        DB::enableQueryLog();

        $this->actingAs($this->owner)
            ->withSession([BranchContext::SESSION_KEY => $this->branch->getKey()])
            ->get($url)
            ->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(
            $budget,
            $count,
            "{$label} chạy {$count} truy vấn (ngưỡng {$budget}), nghi ngờ lỗi N+1.",
        );
    }
}
