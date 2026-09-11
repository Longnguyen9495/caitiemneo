@if ($payroll->dailyKpiResults->isNotEmpty())
    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>KPI doanh thu theo ngày</h2>
                <p>Doanh thu tính theo hóa đơn đã thanh toán trong ngày tại từng chi nhánh. Mỗi ngày chỉ nhận mốc cao nhất đạt được.</p>
            </div>
            <span>{{ \App\Support\Money::format($payroll->daily_kpi_bonus) }}</span>
        </header>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Ngày</th><th>Chi nhánh</th><th class="admin-numeric">Doanh thu</th><th>Mốc đạt được</th><th class="admin-numeric">Thưởng</th></tr></thead>
                <tbody>
                    @foreach ($payroll->dailyKpiResults->sortBy('work_date') as $result)
                        <tr>
                            <td><strong>{{ $result->work_date->format('d/m/Y') }}</strong></td>
                            <td><span class="admin-branch-chip">{{ $result->branch?->code }}</span></td>
                            <td class="admin-numeric"><x-admin.money :value="$result->eligible_revenue" /></td>
                            <td>
                                @if ($result->tier)
                                    Từ {{ \App\Support\Money::format($result->tier->revenue_from) }}
                                    {{ $result->tier->revenue_to ? 'đến dưới '.\App\Support\Money::format($result->tier->revenue_to) : 'trở lên' }}
                                @else
                                    <span class="admin-muted-text">Chưa đạt mốc</span>
                                @endif
                            </td>
                            <td class="admin-numeric"><x-admin.money :value="$result->reward_amount" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
