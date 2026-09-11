<x-layouts.admin title="Báo cáo" heading="Báo cáo">
    <x-admin.page-header
        :title="'Kỳ '.$period->label().' · '.$scopeLabel"
        description="Doanh thu tính theo thời điểm thanh toán hóa đơn; dòng tiền tính theo thời điểm phát sinh giao dịch."
        :breadcrumbs="['Tổng quan' => route('admin.dashboard'), 'Báo cáo' => null]"
    />

    <section class="card overflow-hidden mb-3">
        <x-admin.filter-bar :action="route('admin.reports.index')" submit-label="Xem báo cáo">
            <div class="col-6 col-lg-3">
                <label class="form-label" for="from">Từ ngày</label>
                <input class="form-control" id="from" type="date" name="from" value="{{ $period->from->toDateString() }}">
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label" for="to">Đến ngày</label>
                <input class="form-control" id="to" type="date" name="to" value="{{ $period->to->toDateString() }}">
            </div>
        </x-admin.filter-bar>

        <div class="card-body">
            <div class="row g-2">
                <div class="col-6 col-lg-3">
                    <dl class="neo-stat mb-0">
                        <dt>Doanh thu đã thanh toán</dt>
                        <dd class="fs-6"><x-admin.money :value="$summary['revenue']" /></dd>
                        <small>{{ $summary['invoice_count'] }} hóa đơn</small>
                    </dl>
                </div>
                <div class="col-6 col-lg-3"><dl class="neo-stat mb-0"><dt>Tổng thu</dt><dd class="fs-6"><x-admin.money :value="$summary['cash_income']" /></dd></dl></div>
                <div class="col-6 col-lg-3"><dl class="neo-stat mb-0"><dt>Tổng chi</dt><dd class="fs-6"><x-admin.money :value="$summary['cash_expense']" /></dd></dl></div>
                <div class="col-6 col-lg-3"><dl class="neo-stat mb-0"><dt>Số dư ròng</dt><dd class="fs-6"><x-admin.money :value="$summary['cash_net']" signed /></dd></dl></div>
                <div class="col-6 col-lg-3"><dl class="neo-stat mb-0"><dt>Chi phí nhập kho</dt><dd class="fs-6"><x-admin.money :value="$summary['inventory_cost']" /></dd></dl></div>
                <div class="col-6 col-lg-3"><dl class="neo-stat mb-0"><dt>Chi phí lương đã trả</dt><dd class="fs-6"><x-admin.money :value="$summary['payroll_cost']" /></dd></dl></div>
                <div class="col-6 col-lg-3"><dl class="neo-stat mb-0"><dt>Hoa hồng trong giờ</dt><dd class="fs-6"><x-admin.money :value="$commission['regular']" /></dd></dl></div>
                <div class="col-6 col-lg-3"><dl class="neo-stat mb-0"><dt>Hoa hồng ngoài giờ</dt><dd class="fs-6"><x-admin.money :value="$commission['overtime']" /></dd></dl></div>
                <div class="col-6 col-lg-3"><dl class="neo-stat mb-0"><dt>Thưởng KPI doanh thu</dt><dd class="fs-6"><x-admin.money :value="$kpiBonus" /></dd></dl></div>
                @if ($canSeeProfit)
                    <div class="col-6 col-lg-3"><dl class="neo-stat mb-0"><dt>Lãi gộp ước tính</dt><dd class="fs-6"><x-admin.money :value="$summary['gross_margin']" signed /></dd></dl></div>
                @endif
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.reports.export.invoices', $period->toQuery()) }}">Xuất hóa đơn</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.reports.export.cash', $period->toQuery()) }}">Xuất thu chi</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.reports.export.inventory', $period->toQuery()) }}">Xuất kho</a>
                @can('manage-payroll')
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.reports.export.payrolls', $period->toQuery()) }}">Xuất bảng lương</a>
                @endcan
            </div>
        </div>
    </section>

    <section class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <div>
                <h2 class="neo-display fs-5 mb-0">Lịch hẹn theo trạng thái</h2>
                <p class="mb-0 small text-body-secondary">Tổng {{ $totalAppointments }} lịch trong kỳ.</p>
            </div>
            <span class="badge rounded-pill text-bg-light border">Hủy / vắng: {{ $lostRate }}%</span>
        </div>

        <div class="card-body">
            @foreach (App\Enums\AppointmentStatus::cases() as $status)
                @php
                    $count = $appointmentsByStatus[$status->value] ?? 0;
                    $percent = $totalAppointments > 0 ? round($count / $totalAppointments * 100, 1) : 0;
                @endphp
                <div class="d-flex align-items-center gap-3 py-1">
                    <span class="small" style="flex:0 0 8rem">{{ $status->label() }}</span>
                    <span class="flex-grow-1 rounded-pill overflow-hidden" style="height:8px;background:rgba(189,78,120,.12)">
                        <span class="d-block h-100 rounded-pill bg-primary" style="width:{{ $percent }}%"></span>
                    </span>
                    <strong class="neo-num" style="flex:0 0 2.5rem;text-align:right">{{ $count }}</strong>
                </div>
            @endforeach
        </div>
    </section>

    @include('admin.reports.partials.branch-comparison')

    @include('admin.reports.partials.breakdowns')
</x-layouts.admin>
