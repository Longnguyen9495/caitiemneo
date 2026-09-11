<x-layouts.admin title="Tổng quan" heading="Tổng quan hôm nay">
    <section class="admin-dashboard-grid">
        <article class="admin-stat-card">
            <p>Doanh thu đã thanh toán</p>
            <strong>{{ \App\Support\Money::format($todayRevenue) }}</strong>
            <span>Từ hóa đơn hoàn tất hôm nay</span>
        </article>
        <article class="admin-stat-card">
            <p>Lịch hẹn hôm nay</p>
            <strong>{{ $todayAppointments }}</strong>
            <span>Toàn bộ trạng thái lịch hẹn</span>
        </article>
        <article class="admin-stat-card">
            <p>Vật tư cần lưu ý</p>
            <strong>{{ $lowStockProducts }}</strong>
            <span>Tồn kho bằng hoặc dưới định mức</span>
        </article>
        <article class="admin-stat-card">
            <p>Số dư thu chi</p>
            <strong>{{ \App\Support\Money::format($cashBalance) }}</strong>
            <span>Tổng thu trừ tổng chi</span>
        </article>
    </section>

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div><h2>Lịch hẹn sắp tới</h2><p>Các lịch đang chờ xác nhận, đã xác nhận hoặc đã check-in.</p></div>
            <span>{{ $upcomingAppointments->count() }} lịch</span>
        </header>
        <div class="admin-list">
            @forelse ($upcomingAppointments as $appointment)
                <article>
                    <div><strong>{{ $appointment->customer_name }}</strong><p>{{ $appointment->starts_at->format('H:i · d/m/Y') }} · {{ $appointment->duration_minutes }} phút</p></div>
                    <span>{{ $appointment->employee?->name ?? 'Chưa phân công' }}</span>
                </article>
            @empty
                <p class="admin-empty">Chưa có lịch hẹn sắp tới.</p>
            @endforelse
        </div>
    </section>
</x-layouts.admin>
