@php
    use App\Enums\PayrollAdjustmentCategory as Category;
    use App\Enums\PayrollAdjustmentDirection as Direction;

    $byCategory = fn (Category $category, Direction $direction) => $payroll->adjustments
        ->where('category', $category)
        ->where('direction', $direction)
        ->sum(fn ($row) => (float) $row->amount);
@endphp

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <h2>Phiếu lương</h2>
            <p>Tổng thực lĩnh được làm tròn lên bội số 1.000 đ một lần duy nhất, sau khi đã cộng trừ toàn bộ khoản mục.</p>
        </div>
    </header>

    <div class="admin-panel-body">
        <div class="admin-payslip-groups">
            <div class="admin-payslip-group">
                <h3>Thu nhập cố định</h3>
                <div class="admin-payslip-line"><span>1. Lương cứng</span><x-admin.money :value="$payroll->base_salary" /></div>
                <div class="admin-payslip-line">
                    <span>2. Tiền ca <small>{{ rtrim(rtrim((string) $payroll->shift_count, '0'), '.') }} ca × {{ \App\Support\Money::format($payroll->shift_rate) }}</small></span>
                    <x-admin.money :value="$payroll->shift_pay" />
                </div>
                <div class="admin-payslip-line"><span>3. Thưởng chuyên cần</span><x-admin.money :value="$payroll->attendance_bonus" /></div>
            </div>

            <div class="admin-payslip-group">
                <h3>Hoa hồng</h3>
                <div class="admin-payslip-line">
                    <span>4. Hoa hồng trong giờ <small>Doanh thu {{ \App\Support\Money::format($payroll->regular_revenue) }}</small></span>
                    <x-admin.money :value="$payroll->regular_commission_pay" />
                </div>
                <div class="admin-payslip-line">
                    <span>5. Hoa hồng ngoài giờ <small>Doanh thu {{ \App\Support\Money::format($payroll->overtime_revenue) }}</small></span>
                    <x-admin.money :value="$payroll->overtime_commission_pay" />
                </div>
            </div>

            <div class="admin-payslip-group">
                <h3>Thưởng KPI</h3>
                <div class="admin-payslip-line">
                    <span>6. KPI doanh thu theo ngày <small>{{ $payroll->dailyKpiResults->where('reward_amount', '>', 0)->count() }} ngày đạt mốc</small></span>
                    <x-admin.money :value="$payroll->daily_kpi_bonus" />
                </div>
                <div class="admin-payslip-line">
                    <span>7. KPI số bill <small>{{ $payroll->qualified_bill_count }} bill hợp lệ{{ $payroll->policy?->required_bill_count ? ' / cần '.$payroll->policy->required_bill_count : '' }}</small></span>
                    <x-admin.money :value="$payroll->bill_kpi_bonus" />
                </div>
            </div>

            <div class="admin-payslip-group">
                <h3>Khoản cộng thêm</h3>
                <div class="admin-payslip-line"><span>8. Phụ cấp</span><x-admin.money :value="$byCategory(Category::Allowance, Direction::Earning)" /></div>
                <div class="admin-payslip-line"><span>9. Thưởng khác</span><x-admin.money :value="$byCategory(Category::ManualBonus, Direction::Earning) + $byCategory(Category::OvertimeBonus, Direction::Earning)" /></div>
                <div class="admin-payslip-line"><span>13. Điều chỉnh kỳ trước (cộng)</span><x-admin.money :value="$byCategory(Category::Correction, Direction::Earning)" /></div>
            </div>

            <div class="admin-payslip-group">
                <h3>Khoản trừ</h3>
                <div class="admin-payslip-line"><span>10. Phạt</span><x-admin.money :value="$byCategory(Category::Penalty, Direction::Deduction) + $byCategory(Category::MissingBillPenalty, Direction::Deduction)" /></div>
                <div class="admin-payslip-line"><span>11. Tạm ứng</span><x-admin.money :value="$byCategory(Category::SalaryAdvance, Direction::Deduction)" /></div>
                <div class="admin-payslip-line"><span>12. Khấu trừ khác</span><x-admin.money :value="$byCategory(Category::Other, Direction::Deduction)" /></div>
                <div class="admin-payslip-line"><span>Điều chỉnh kỳ trước (trừ)</span><x-admin.money :value="$byCategory(Category::Correction, Direction::Deduction)" /></div>
            </div>

            <div class="admin-payslip-group">
                <h3>Chốt lương</h3>
                <div class="admin-payslip-line is-total"><span>14. Tổng hệ thống tính</span><x-admin.money :value="$payroll->calculated_total" /></div>
                <div class="admin-payslip-line">
                    <span>15. Điều chỉnh được duyệt
                        @if ($payroll->variance_reason)<small>{{ $payroll->variance_reason }} · {{ $payroll->approver?->name }}</small>@endif
                    </span>
                    <x-admin.money :value="$payroll->approved_manual_adjustment" signed />
                </div>
                <div class="admin-payslip-line is-total"><span>16. Tổng trước làm tròn</span><x-admin.money :value="$payroll->unrounded_final_total" /></div>
                <div class="admin-payslip-line">
                    <span>17. Làm tròn lên 1.000 đ <small>{{ $payroll->rounding_rule->label() }}</small></span>
                    <x-admin.money :value="$payroll->rounding_adjustment" signed />
                </div>
                <div class="admin-payslip-line is-grand"><span>18. Thực lĩnh</span><x-admin.money :value="$payroll->final_total" /></div>
            </div>
        </div>
    </div>
</section>
