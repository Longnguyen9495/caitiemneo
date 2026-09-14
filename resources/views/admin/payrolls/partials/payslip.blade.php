@php
    use App\Enums\PayrollAdjustmentCategory as Category;
    use App\Enums\PayrollAdjustmentDirection as Direction;

    $byCategory = fn (Category $category, Direction $direction) => $payroll->adjustments
        ->where('category', $category)
        ->where('direction', $direction)
        ->sum(fn ($row) => (float) $row->amount);

    $groups = [
        'Thu nhập cố định' => [
            ['1. Lương cứng', $payroll->base_salary, $payroll->calendar_days ? $payroll->calendar_days.' ngày theo lịch · '.\App\Support\Money::format($payroll->daily_base_salary_rate).'/ngày' : null],
            ['2. Tiền ca', $payroll->shift_pay, rtrim(rtrim((string) $payroll->shift_count, '0'), '.').' ca × '.\App\Support\Money::format($payroll->shift_rate)],
            ['3. Thưởng chuyên cần', $payroll->attendance_bonus, null],
        ],
        'Hoa hồng' => [
            ['4. Hoa hồng trong giờ', $payroll->regular_commission_pay, 'Doanh thu '.\App\Support\Money::format($payroll->regular_revenue)],
            ['5. Hoa hồng ngoài giờ', $payroll->overtime_commission_pay, 'Doanh thu '.\App\Support\Money::format($payroll->overtime_revenue)],
        ],
        'Thưởng KPI' => [
            ['6. KPI doanh thu theo ngày', $payroll->daily_kpi_bonus, $payroll->dailyKpiResults->where('reward_amount', '>', 0)->count().' ngày đạt mốc'],
            ['7. KPI số bill', $payroll->bill_kpi_bonus, $payroll->qualified_bill_count.' bill hợp lệ'.($payroll->policy?->required_bill_count ? ' / cần '.$payroll->policy->required_bill_count : '')],
        ],
        'Khoản cộng thêm' => [
            ['8. Thưởng đi làm ngày nghỉ hưởng lương', $byCategory(Category::WorkedPaidLeaveBonus, Direction::Earning), $payroll->worked_paid_leave_days ? $payroll->worked_paid_leave_days.' ngày × '.\App\Support\Money::format($payroll->worked_paid_leave_bonus_rate) : null],
            ['9. Phụ cấp', $byCategory(Category::Allowance, Direction::Earning), null],
            ['10. Thưởng khác', $byCategory(Category::ManualBonus, Direction::Earning) + $byCategory(Category::OvertimeBonus, Direction::Earning), null],
            ['14. Điều chỉnh kỳ trước', $byCategory(Category::Correction, Direction::Earning), 'Khoản cộng'],
        ],
        'Khoản trừ' => [
            ['11. Nghỉ không hưởng lương', $byCategory(Category::UnpaidLeaveDeduction, Direction::Deduction), $payroll->unpaid_leave_days ? $payroll->unpaid_leave_days.' ngày · còn '.$payroll->required_work_days.' ngày công yêu cầu sau '.$payroll->paid_leave_days.' ngày nghỉ hưởng lương đã xếp' : null],
            ['12. Phạt', $byCategory(Category::Penalty, Direction::Deduction) + $byCategory(Category::MissingBillPenalty, Direction::Deduction), null],
            ['13. Tạm ứng', $byCategory(Category::SalaryAdvance, Direction::Deduction), null],
            ['Khấu trừ khác', $byCategory(Category::Other, Direction::Deduction), null],
            ['Điều chỉnh kỳ trước', $byCategory(Category::Correction, Direction::Deduction), 'Khoản trừ'],
        ],
    ];
@endphp

<section class="card mb-3">
    <div class="card-header">
        <h2 class="neo-display fs-5 mb-0">Phiếu lương</h2>
        <p class="mb-0 small text-body-secondary">Tổng thực lĩnh được làm tròn lên bội số 1.000 đ một lần duy nhất, sau khi cộng trừ toàn bộ khoản mục.</p>
    </div>

    <div class="card-body">
        @foreach ($groups as $groupTitle => $lines)
            <h3 class="fs-6 fw-semibold text-body-secondary mt-3 mb-2">{{ $groupTitle }}</h3>
            <ul class="list-unstyled mb-0">
                @foreach ($lines as [$label, $amount, $hint])
                    <li class="d-flex justify-content-between align-items-baseline gap-3 py-2 border-bottom">
                        <span>
                            {{ $label }}
                            @if ($hint)<small class="d-block text-body-secondary">{{ $hint }}</small>@endif
                        </span>
                        <x-admin.money :value="$amount" class="fw-medium" />
                    </li>
                @endforeach
            </ul>
        @endforeach

        <h3 class="fs-6 fw-semibold text-body-secondary mt-4 mb-2">Chốt lương</h3>
        <ul class="list-unstyled mb-0">
            <li class="d-flex justify-content-between align-items-baseline gap-3 py-2 border-bottom fw-semibold">
                <span>14. Tổng hệ thống tính</span>
                <x-admin.money :value="$payroll->calculated_total" />
            </li>
            <li class="d-flex justify-content-between align-items-baseline gap-3 py-2 border-bottom">
                <span>
                    15. Điều chỉnh được duyệt
                    @if ($payroll->variance_reason)
                        <small class="d-block text-body-secondary">{{ $payroll->variance_reason }} · {{ $payroll->approver?->name }}</small>
                    @endif
                </span>
                <x-admin.money :value="$payroll->approved_manual_adjustment" signed />
            </li>
            <li class="d-flex justify-content-between align-items-baseline gap-3 py-2 border-bottom fw-semibold">
                <span>16. Tổng trước làm tròn</span>
                <x-admin.money :value="$payroll->unrounded_final_total" />
            </li>
            <li class="d-flex justify-content-between align-items-baseline gap-3 py-2 border-bottom">
                <span>
                    17. Làm tròn lên 1.000 đ
                    <small class="d-block text-body-secondary">{{ $payroll->rounding_rule->label() }}</small>
                </span>
                <x-admin.money :value="$payroll->rounding_adjustment" signed />
            </li>
            <li class="d-flex justify-content-between align-items-baseline gap-3 mt-2 p-3 rounded-3 text-bg-primary">
                <span class="fw-semibold">18. Thực lĩnh</span>
                <strong class="fs-5 neo-num">{{ \App\Support\Money::format($payroll->final_total) }}</strong>
            </li>
        </ul>
    </div>
</section>
