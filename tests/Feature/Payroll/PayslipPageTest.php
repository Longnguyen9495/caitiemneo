<?php

namespace Tests\Feature\Payroll;

use App\Actions\Payrolls\CalculatePayrollAction;
use App\Enums\AttendanceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\WorkContext;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payroll;
use App\Models\PayrollPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_payslip_shows_every_line_of_the_breakdown(): void
    {
        $branch = Branch::factory()->create(['code' => 'CN-PS', 'name' => 'Cơ sở phiếu lương']);
        PayrollPolicy::factory()->withStandardKpiTiers()->create([
            'attendance_bonus_amount' => 200000,
            'allowed_absence_days' => 2,
        ]);

        $owner = User::factory()->owner()->create();
        $employee = User::factory()->employee()->withoutBranch()->atBranch($branch)->create([
            'base_salary' => 5000000,
            'shift_rate' => 200000,
        ]);

        AttendanceRecord::factory()->create([
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-08-01',
            'shift_name' => 'Ca sáng',
            'shift_value' => 1,
            'status' => AttendanceStatus::Present,
        ]);

        $invoice = Invoice::factory()->create([
            'branch_id' => $branch->id,
            'status' => InvoiceStatus::Paid,
            'paid_at' => '2026-08-05 12:00:00',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'employee_id' => $employee->id,
            'work_context' => WorkContext::Overtime,
            'line_total' => 1800000,
            'commission_amount' => 360000,
        ]);

        $payroll = app(CalculatePayrollAction::class)->refresh(
            Payroll::factory()->create([
                'employee_id' => $employee->id,
                'paying_branch_id' => $branch->id,
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
            ])
        );

        $response = $this->actingAs($owner)->get(route('admin.payrolls.show', $payroll))->assertOk();

        foreach ([
            'Lương cứng',
            'Tiền ca',
            'Thưởng chuyên cần',
            'Hoa hồng trong giờ',
            'Hoa hồng ngoài giờ',
            'KPI doanh thu theo ngày',
            'KPI số bill',
            'Phụ cấp',
            'Thưởng khác',
            'Phạt',
            'Tạm ứng',
            'Khấu trừ khác',
            'Điều chỉnh kỳ trước',
            'Tổng hệ thống tính',
            'Điều chỉnh được duyệt',
            'Tổng trước làm tròn',
            'Làm tròn lên 1.000 đ',
            'Thực lĩnh',
            'Phân bổ theo chi nhánh',
        ] as $label) {
            $response->assertSee($label);
        }

        // 5.000.000 + 200.000 ca + 200.000 chuyên cần + 360.000 hoa hồng ngoài giờ + 100.000 KPI.
        $this->assertSame('5860000.00', $payroll->calculated_total);
        $response->assertSee('5.860.000');
    }

    public function test_an_employee_can_read_their_own_payslip_but_not_someone_elses(): void
    {
        $branch = Branch::factory()->create(['code' => 'CN-PS2']);
        PayrollPolicy::factory()->create(['attendance_bonus_amount' => 0, 'allowed_absence_days' => 31]);

        $mine = User::factory()->employee()->withoutBranch()->atBranch($branch)->create();
        $other = User::factory()->employee()->withoutBranch()->atBranch($branch)->create();

        $minePayroll = Payroll::factory()->create(['employee_id' => $mine->id, 'paying_branch_id' => $branch->id]);
        $otherPayroll = Payroll::factory()->create(['employee_id' => $other->id, 'paying_branch_id' => $branch->id]);

        $this->actingAs($mine)->get(route('admin.payrolls.show', $minePayroll))->assertOk();
        $this->actingAs($mine)->get(route('admin.payrolls.show', $otherPayroll))->assertForbidden();
    }
}
