@if ($branchComparison->isNotEmpty())
    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>So sánh chi nhánh</h2>
                <p>Doanh thu theo ngày thanh toán hóa đơn; dòng tiền theo ngày phát sinh giao dịch. Hai chỉ số không trộn lẫn.</p>
            </div>
            <span>{{ $branchComparison->count() }} chi nhánh</span>
        </header>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Chi nhánh</th>
                        <th class="admin-numeric">Doanh thu</th>
                        <th class="admin-numeric">Tổng thu</th>
                        <th class="admin-numeric">Tổng chi</th>
                        <th class="admin-numeric">Dòng tiền ròng</th>
                        <th class="admin-numeric">Số lịch</th>
                        <th class="admin-numeric">Hủy / vắng</th>
                        <th class="admin-numeric">HH trong giờ</th>
                        <th class="admin-numeric">HH ngoài giờ</th>
                        <th class="admin-numeric">Thưởng KPI</th>
                        <th class="admin-numeric">Chi phí lương</th>
                        <th class="admin-numeric">Nhập kho</th>
                        <th class="admin-numeric">Tồn thấp</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($branchComparison as $row)
                        <tr>
                            <td><span class="admin-branch-chip">{{ $row['branch']->code }}</span> <strong>{{ $row['branch']->name }}</strong></td>
                            <td class="admin-numeric"><x-admin.money :value="$row['summary']['revenue']" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$row['summary']['cash_income']" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$row['summary']['cash_expense']" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$row['summary']['cash_net']" signed /></td>
                            <td class="admin-numeric">{{ $row['appointments'] }}</td>
                            <td class="admin-numeric">{{ $row['lost'] }} <small>({{ $row['lost_rate'] }}%)</small></td>
                            <td class="admin-numeric"><x-admin.money :value="$row['regular_commission']" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$row['overtime_commission']" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$row['kpi_bonus']" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$row['summary']['payroll_cost']" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$row['summary']['inventory_cost']" /></td>
                            <td class="admin-numeric">{{ $row['low_stock'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
