<section class="card mb-3">
    <div class="card-header">
        <h2 class="neo-display fs-5 mb-0">Doanh thu theo dịch vụ</h2>
        <p class="mb-0 small text-body-secondary">Xếp theo doanh thu giảm dần, tối đa 15 dịch vụ.</p>
    </div>

    <table class="table neo-table align-middle mb-0">
        <thead>
            <tr>
                <th scope="col">Dịch vụ</th>
                <th scope="col" class="text-end">Lượt</th>
                <th scope="col" class="text-end">Doanh thu</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($revenueByService as $row)
                <tr>
                    <td class="fw-semibold">{{ $row->service_name }}</td>
                    <td data-label="Lượt" class="text-end neo-num">{{ rtrim(rtrim((string) $row->quantity_total, '0'), '.') }}</td>
                    <td data-label="Doanh thu" class="text-end fw-semibold"><x-admin.money :value="$row->revenue_total" /></td>
                </tr>
            @empty
                <x-admin.empty-state :colspan="3" icon="chart" title="Chưa có doanh thu dịch vụ trong kỳ" />
            @endforelse
        </tbody>
    </table>
</section>

<section class="card">
    <div class="card-header">
        <h2 class="neo-display fs-5 mb-0">Doanh thu và hoa hồng theo nhân viên</h2>
        <p class="mb-0 small text-body-secondary">Tính trên các hóa đơn đã thanh toán trong kỳ.</p>
    </div>

    <table class="table neo-table align-middle mb-0">
        <thead>
            <tr>
                <th scope="col">Nhân viên</th>
                <th scope="col" class="text-end">Doanh thu</th>
                <th scope="col" class="text-end">Hoa hồng</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($revenueByEmployee as $row)
                <tr>
                    <td class="fw-semibold">{{ $row->employee_name }}</td>
                    <td data-label="Doanh thu" class="text-end"><x-admin.money :value="$row->revenue_total" /></td>
                    <td data-label="Hoa hồng" class="text-end fw-semibold"><x-admin.money :value="$row->commission_total" /></td>
                </tr>
            @empty
                <x-admin.empty-state :colspan="3" icon="people" title="Chưa có dữ liệu nhân viên trong kỳ" />
            @endforelse
        </tbody>
    </table>
</section>
