@if ($branchComparison->isNotEmpty())
    <section class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <div>
                <h2 class="neo-display fs-5 mb-0">So sánh chi nhánh</h2>
                <p class="mb-0 small text-body-secondary">Doanh thu theo ngày thanh toán; dòng tiền theo ngày phát sinh. Hai chỉ số không trộn lẫn.</p>
            </div>
            <span class="badge rounded-pill text-bg-light border">{{ $branchComparison->count() }}</span>
        </div>

        <table class="table neo-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Chi nhánh</th>
                    <th scope="col" class="text-end">Doanh thu</th>
                    <th scope="col" class="text-end">Tổng thu</th>
                    <th scope="col" class="text-end">Tổng chi</th>
                    <th scope="col" class="text-end">Dòng tiền ròng</th>
                    <th scope="col" class="text-end">Số lịch</th>
                    <th scope="col" class="text-end">Hủy / vắng</th>
                    <th scope="col" class="text-end">HH trong giờ</th>
                    <th scope="col" class="text-end">HH ngoài giờ</th>
                    <th scope="col" class="text-end">Thưởng KPI</th>
                    <th scope="col" class="text-end">Chi phí lương</th>
                    <th scope="col" class="text-end">Nhập kho</th>
                    <th scope="col" class="text-end">Tồn thấp</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($branchComparison as $row)
                    <tr>
                        <td>
                            <span class="badge rounded-pill text-bg-light border fw-normal me-1">{{ $row['branch']->code }}</span>
                            <span class="fw-semibold">{{ $row['branch']->name }}</span>
                        </td>
                        <td data-label="Doanh thu" class="text-end fw-semibold"><x-admin.money :value="$row['summary']['revenue']" /></td>
                        <td data-label="Tổng thu" class="text-end"><x-admin.money :value="$row['summary']['cash_income']" /></td>
                        <td data-label="Tổng chi" class="text-end"><x-admin.money :value="$row['summary']['cash_expense']" /></td>
                        <td data-label="Dòng tiền ròng" class="text-end"><x-admin.money :value="$row['summary']['cash_net']" signed /></td>
                        <td data-label="Số lịch" class="text-end neo-num">{{ $row['appointments'] }}</td>
                        <td data-label="Hủy / vắng" class="text-end neo-num">{{ $row['lost'] }} <small class="text-body-secondary">({{ $row['lost_rate'] }}%)</small></td>
                        <td data-label="HH trong giờ" class="text-end"><x-admin.money :value="$row['regular_commission']" /></td>
                        <td data-label="HH ngoài giờ" class="text-end"><x-admin.money :value="$row['overtime_commission']" /></td>
                        <td data-label="Thưởng KPI" class="text-end"><x-admin.money :value="$row['kpi_bonus']" /></td>
                        <td data-label="Chi phí lương" class="text-end"><x-admin.money :value="$row['summary']['payroll_cost']" /></td>
                        <td data-label="Nhập kho" class="text-end"><x-admin.money :value="$row['summary']['inventory_cost']" /></td>
                        <td data-label="Tồn thấp" class="text-end neo-num">{{ $row['low_stock'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endif
