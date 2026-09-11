<section class="admin-panel">
    <header class="admin-panel-header">
        <div><h2>Doanh thu theo dịch vụ</h2><p>Xếp theo doanh thu giảm dần, tối đa 15 dịch vụ.</p></div>
    </header>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Dịch vụ</th><th class="admin-numeric">Lượt</th><th class="admin-numeric">Doanh thu</th></tr></thead>
            <tbody>
                @forelse ($revenueByService as $row)
                    <tr>
                        <td><strong>{{ $row->service_name }}</strong></td>
                        <td class="admin-numeric">{{ rtrim(rtrim((string) $row->quantity_total, '0'), '.') }}</td>
                        <td class="admin-numeric"><x-admin.money :value="$row->revenue_total" /></td>
                    </tr>
                @empty
                    <x-admin.empty-state :colspan="3" title="Chưa có doanh thu dịch vụ trong kỳ" />
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div><h2>Doanh thu và hoa hồng theo nhân viên</h2><p>Tính trên các hóa đơn đã thanh toán trong kỳ.</p></div>
    </header>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Nhân viên</th><th class="admin-numeric">Doanh thu</th><th class="admin-numeric">Hoa hồng</th></tr></thead>
            <tbody>
                @forelse ($revenueByEmployee as $row)
                    <tr>
                        <td><strong>{{ $row->employee_name }}</strong></td>
                        <td class="admin-numeric"><x-admin.money :value="$row->revenue_total" /></td>
                        <td class="admin-numeric"><x-admin.money :value="$row->commission_total" /></td>
                    </tr>
                @empty
                    <x-admin.empty-state :colspan="3" title="Chưa có dữ liệu nhân viên trong kỳ" />
                @endforelse
            </tbody>
        </table>
    </div>
</section>
