@if ($payroll->allocations->count() > 0)
    <section class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <div>
                <h2 class="neo-display fs-5 mb-0">Phân bổ theo chi nhánh</h2>
                <p class="mb-0 small text-body-secondary">Lương cứng và thưởng theo kỳ chia theo số ca làm tại từng cơ sở.</p>
            </div>
            <span class="badge rounded-pill text-bg-light border">{{ $payroll->allocations->count() }}</span>
        </div>

        <table class="table neo-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Chi nhánh</th>
                    <th scope="col" class="text-end">Số ca</th>
                    <th scope="col" class="text-end">Lương cứng</th>
                    <th scope="col" class="text-end">Tiền ca</th>
                    <th scope="col" class="text-end">Chuyên cần</th>
                    <th scope="col" class="text-end">HH trong giờ</th>
                    <th scope="col" class="text-end">HH ngoài giờ</th>
                    <th scope="col" class="text-end">KPI ngày</th>
                    <th scope="col" class="text-end">KPI bill</th>
                    <th scope="col" class="text-end">Cộng khác</th>
                    <th scope="col" class="text-end">Trừ</th>
                    <th scope="col" class="text-end">Cộng dồn</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payroll->allocations as $allocation)
                    <tr>
                        <td>
                            <span class="badge rounded-pill text-bg-light border fw-normal me-1">{{ $allocation->branch?->code }}</span>
                            <span class="fw-semibold">{{ $allocation->branch?->name }}</span>
                        </td>
                        <td data-label="Số ca" class="text-end neo-num">{{ rtrim(rtrim((string) $allocation->shift_count, '0'), '.') }}</td>
                        <td data-label="Lương cứng" class="text-end"><x-admin.money :value="$allocation->base_salary_amount" /></td>
                        <td data-label="Tiền ca" class="text-end"><x-admin.money :value="$allocation->shift_pay" /></td>
                        <td data-label="Chuyên cần" class="text-end"><x-admin.money :value="$allocation->attendance_bonus" /></td>
                        <td data-label="HH trong giờ" class="text-end"><x-admin.money :value="$allocation->regular_commission_pay" /></td>
                        <td data-label="HH ngoài giờ" class="text-end"><x-admin.money :value="$allocation->overtime_commission_pay" /></td>
                        <td data-label="KPI ngày" class="text-end"><x-admin.money :value="$allocation->daily_kpi_bonus" /></td>
                        <td data-label="KPI bill" class="text-end"><x-admin.money :value="$allocation->bill_kpi_bonus" /></td>
                        <td data-label="Cộng khác" class="text-end"><x-admin.money :value="(float) $allocation->allowance_total + (float) $allocation->bonus_total" /></td>
                        <td data-label="Trừ" class="text-end"><x-admin.money :value="$allocation->deduction_total" /></td>
                        <td data-label="Cộng dồn" class="text-end fw-bold"><x-admin.money :value="$allocation->subtotal" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endif
