<x-layouts.admin title="Báo cáo" heading="Báo cáo vận hành">
    <x-admin.page-header
        title="Báo cáo kỳ {{ $period->label() }} · {{ $scopeLabel }}"
        description="Doanh thu tính theo thời điểm thanh toán hóa đơn; dòng tiền tính theo thời điểm phát sinh giao dịch."
        :breadcrumbs="['Tổng quan' => route('admin.dashboard'), 'Báo cáo' => null]"
    />

    <section class="admin-panel">
        <x-admin.filter-bar :action="route('admin.reports.index')" submit-label="Xem báo cáo">
            <label>Từ ngày<input type="date" name="from" value="{{ $period->from->toDateString() }}"></label>
            <label>Đến ngày<input type="date" name="to" value="{{ $period->to->toDateString() }}"></label>
        </x-admin.filter-bar>

        <div class="admin-summary-grid">
            <div><p>Doanh thu đã thanh toán</p><strong><x-admin.money :value="$summary['revenue']" /></strong><p>{{ $summary['invoice_count'] }} hóa đơn</p></div>
            <div><p>Tổng thu</p><strong><x-admin.money :value="$summary['cash_income']" /></strong></div>
            <div><p>Tổng chi</p><strong><x-admin.money :value="$summary['cash_expense']" /></strong></div>
            <div><p>Số dư ròng</p><strong><x-admin.money :value="$summary['cash_net']" signed /></strong></div>
            <div><p>Chi phí nhập kho</p><strong><x-admin.money :value="$summary['inventory_cost']" /></strong></div>
            <div><p>Chi phí lương đã trả</p><strong><x-admin.money :value="$summary['payroll_cost']" /></strong></div>
            <div><p>Hoa hồng trong giờ</p><strong><x-admin.money :value="$commission['regular']" /></strong></div>
            <div><p>Hoa hồng ngoài giờ</p><strong><x-admin.money :value="$commission['overtime']" /></strong></div>
            <div><p>Thưởng KPI doanh thu</p><strong><x-admin.money :value="$kpiBonus" /></strong></div>
            @if ($canSeeProfit)
                <div><p>Lãi gộp ước tính</p><strong><x-admin.money :value="$summary['gross_margin']" signed /></strong></div>
            @endif
        </div>

        <div class="admin-panel-body">
            <div class="admin-page-actions">
                <a class="admin-button is-ghost" href="{{ route('admin.reports.export.invoices', $period->toQuery()) }}">Xuất hóa đơn</a>
                <a class="admin-button is-ghost" href="{{ route('admin.reports.export.cash', $period->toQuery()) }}">Xuất thu chi</a>
                <a class="admin-button is-ghost" href="{{ route('admin.reports.export.inventory', $period->toQuery()) }}">Xuất kho</a>
                @can('manage-payroll')
                    <a class="admin-button is-ghost" href="{{ route('admin.reports.export.payrolls', $period->toQuery()) }}">Xuất bảng lương</a>
                @endcan
            </div>
        </div>
    </section>

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div><h2>Lịch hẹn theo trạng thái</h2><p>Tổng {{ $totalAppointments }} lịch trong kỳ.</p></div>
            <span>Tỷ lệ hủy / không đến: {{ $lostRate }}%</span>
        </header>
        <div class="admin-panel-body">
            @forelse (App\Enums\AppointmentStatus::cases() as $status)
                @php $count = $appointmentsByStatus[$status->value] ?? 0; @endphp
                <div class="admin-bar-row">
                    <span>{{ $status->label() }}</span>
                    <span class="admin-bar-track">
                        <span class="admin-bar-fill" style="width: {{ $totalAppointments > 0 ? round($count / $totalAppointments * 100, 1) : 0 }}%"></span>
                    </span>
                    <strong>{{ $count }}</strong>
                </div>
            @empty
                <x-admin.empty-state title="Chưa có lịch hẹn trong kỳ" />
            @endforelse
        </div>
    </section>

    @include('admin.reports.partials.branch-comparison')

    @include('admin.reports.partials.breakdowns')
</x-layouts.admin>
