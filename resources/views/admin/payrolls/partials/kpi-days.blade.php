@if ($payroll->dailyKpiResults->isNotEmpty())
    <section class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <div>
                <h2 class="neo-display fs-5 mb-0">KPI doanh thu theo ngày</h2>
                <p class="mb-0 small text-body-secondary">Mỗi ngày chỉ nhận mốc cao nhất đạt được.</p>
            </div>
            <span class="badge rounded-pill text-bg-success">{{ \App\Support\Money::format($payroll->daily_kpi_bonus) }}</span>
        </div>

        <table class="table neo-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Ngày</th>
                    <th scope="col">Chi nhánh</th>
                    <th scope="col" class="text-end">Doanh thu</th>
                    <th scope="col">Mốc đạt được</th>
                    <th scope="col" class="text-end">Thưởng</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payroll->dailyKpiResults->sortBy('work_date') as $result)
                    <tr>
                        <td class="neo-num fw-semibold">{{ $result->work_date->format('d/m/Y') }}</td>
                        <td data-label="Chi nhánh"><span class="badge rounded-pill text-bg-light border fw-normal">{{ $result->branch?->code }}</span></td>
                        <td data-label="Doanh thu" class="text-end"><x-admin.money :value="$result->eligible_revenue" /></td>
                        <td data-label="Mốc đạt được">
                            @if ($result->tier)
                                Từ {{ \App\Support\Money::format($result->tier->revenue_from) }}
                                {{ $result->tier->revenue_to ? 'đến dưới '.\App\Support\Money::format($result->tier->revenue_to) : 'trở lên' }}
                            @else
                                <span class="text-body-secondary">Chưa đạt mốc</span>
                            @endif
                        </td>
                        <td data-label="Thưởng" class="text-end fw-semibold"><x-admin.money :value="$result->reward_amount" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endif
