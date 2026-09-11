@if ($payroll->allocations->count() > 0)
    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>Phân bổ theo chi nhánh</h2>
                <p>Mỗi cơ sở gánh đúng phần chi phí đã phát sinh tại đó. Lương cứng và thưởng theo kỳ được chia theo số ca làm.</p>
            </div>
            <span>{{ $payroll->allocations->count() }} chi nhánh</span>
        </header>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Chi nhánh</th>
                        <th class="admin-numeric">Số ca</th>
                        <th class="admin-numeric">Lương cứng</th>
                        <th class="admin-numeric">Tiền ca</th>
                        <th class="admin-numeric">Chuyên cần</th>
                        <th class="admin-numeric">HH trong giờ</th>
                        <th class="admin-numeric">HH ngoài giờ</th>
                        <th class="admin-numeric">KPI ngày</th>
                        <th class="admin-numeric">KPI bill</th>
                        <th class="admin-numeric">Cộng khác</th>
                        <th class="admin-numeric">Trừ</th>
                        <th class="admin-numeric">Cộng dồn</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payroll->allocations as $allocation)
                        <tr>
                            <td><span class="admin-branch-chip">{{ $allocation->branch?->code }}</span> <strong>{{ $allocation->branch?->name }}</strong></td>
                            <td class="admin-numeric">{{ rtrim(rtrim((string) $allocation->shift_count, '0'), '.') }}</td>
                            <td class="admin-numeric"><x-admin.money :value="$allocation->base_salary_amount" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$allocation->shift_pay" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$allocation->attendance_bonus" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$allocation->regular_commission_pay" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$allocation->overtime_commission_pay" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$allocation->daily_kpi_bonus" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$allocation->bill_kpi_bonus" /></td>
                            <td class="admin-numeric"><x-admin.money :value="(float) $allocation->allowance_total + (float) $allocation->bonus_total" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$allocation->deduction_total" /></td>
                            <td class="admin-numeric"><strong><x-admin.money :value="$allocation->subtotal" /></strong></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="11">Tổng phân bổ</th>
                        <th class="admin-numeric"><x-admin.money :value="$payroll->allocations->sum(fn ($row) => (float) $row->subtotal)" /></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
@endif
