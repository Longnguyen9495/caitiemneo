{{-- Chi nhánh đã hiện ở nút chuyển chi nhánh trên thanh trên cùng nên không lặp lại trong tiêu đề. --}}
<x-layouts.admin title="Tổng quan" heading="Tổng quan">
    <section class="mb-3" aria-labelledby="neo-today">
        <h2 id="neo-today" class="visually-hidden">Chỉ số hôm nay</h2>

        <div class="row g-2 g-lg-3">
            @if ($mayViewRevenue)
                <div class="col-6 col-lg-3">
                    <dl class="neo-stat mb-0">
                        <dt>Doanh thu hôm nay</dt>
                        <dd>{{ \App\Support\Money::format($todayRevenue) }}</dd>
                        <small>Hóa đơn đã thanh toán</small>
                    </dl>
                </div>
            @endif
            <div class="col-6 col-lg-3">
                <a href="{{ route('admin.appointments.index') }}" class="neo-stat">
                    <dl class="mb-0">
                        <dt>Lịch hẹn hôm nay</dt>
                        <dd>{{ $todayAppointments }}</dd>
                        <small>Mọi trạng thái</small>
                    </dl>
                </a>
            </div>
            <div class="col-6 col-lg-3">
                <a href="{{ route('admin.products.index', ['low_stock' => 1]) }}" class="neo-stat">
                    <dl class="mb-0">
                        <dt>Vật tư cần lưu ý</dt>
                        <dd>{{ $lowStockProducts }}</dd>
                        <small>Tồn bằng hoặc dưới định mức</small>
                    </dl>
                </a>
            </div>
            @if ($mayViewCash)
                <div class="col-6 col-lg-3">
                    <dl class="neo-stat mb-0">
                        <dt>Số dư thu chi</dt>
                        <dd>{{ \App\Support\Money::format($cashBalance) }}</dd>
                        <small>Tổng thu trừ tổng chi</small>
                    </dl>
                </div>
            @endif
        </div>
    </section>

    <section class="card" aria-labelledby="neo-upcoming">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <div>
                <h2 id="neo-upcoming" class="neo-display fs-5 mb-0">Lịch hẹn sắp tới</h2>
                <p class="mb-0 small text-body-secondary">Đang chờ xác nhận, đã xác nhận hoặc đã đến.</p>
            </div>
            <span class="badge rounded-pill text-bg-light border">{{ $upcomingAppointments->count() }}</span>
        </div>

        @forelse ($upcomingAppointments as $appointment)
            <a href="{{ route('admin.appointments.index', ['date' => $appointment->starts_at->toDateString()]) }}"
               class="list-group-item list-group-item-action border-0 border-bottom d-flex align-items-center gap-3 px-3 py-3">
                <span class="text-center flex-shrink-0" style="width:3.5rem">
                    <span class="d-block fw-bold neo-num">{{ $appointment->starts_at->format('H:i') }}</span>
                    <small class="text-body-secondary">{{ $appointment->starts_at->format('d/m') }}</small>
                </span>
                <span class="flex-grow-1 min-w-0" style="min-width:0">
                    <span class="d-block fw-semibold text-truncate">{{ $appointment->customer_name }}</span>
                    <small class="text-body-secondary">
                        {{ $appointment->duration_minutes }} phút ·
                        {{ $appointment->employee?->name ?? 'Chưa phân công' }}
                    </small>
                </span>
                <x-admin.status-badge :status="$appointment->status" />
            </a>
        @empty
            <x-admin.empty-state
                icon="calendar"
                title="Chưa có lịch hẹn sắp tới"
                hint="Khi khách đặt lịch hoặc bạn tạo lịch mới, chúng sẽ xuất hiện ở đây."
            >
                <a href="{{ route('admin.appointments.create') }}" class="btn btn-primary btn-sm">Tạo lịch hẹn</a>
            </x-admin.empty-state>
        @endforelse
    </section>
</x-layouts.admin>
