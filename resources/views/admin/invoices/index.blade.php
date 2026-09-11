<x-layouts.admin title="Hóa đơn" heading="Hóa đơn & thu chi">
    @include('admin.partials.finance-nav')

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>Danh sách hóa đơn</h2>
                <p>Hóa đơn được tạo từ lịch hẹn đã hoàn tất. Doanh thu ghi nhận theo thời điểm thanh toán.</p>
            </div>
            <a href="{{ route('admin.reports.export.invoices', request()->query()) }}" class="admin-button is-ghost">Xuất CSV</a>
        </header>

        <x-admin.filter-bar :action="route('admin.invoices.index')">
            <label>
                Tìm kiếm
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Số hóa đơn, tên hoặc SĐT">
            </label>
            <label>
                Trạng thái
                <select name="status">
                    <option value="">Tất cả</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Phương thức
                <select name="payment_method">
                    <option value="">Tất cả</option>
                    @foreach ($paymentMethods as $value => $label)
                        <option value="{{ $value }}" @selected(request('payment_method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Từ ngày<input type="date" name="from" value="{{ request('from') }}"></label>
            <label>Đến ngày<input type="date" name="to" value="{{ request('to') }}"></label>
        </x-admin.filter-bar>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Số hóa đơn</th>
                        <th>Chi nhánh</th>
                        <th>Khách hàng</th>
                        <th>Ngày tạo</th>
                        <th class="admin-numeric">Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th>Phương thức</th>
                        <th>Người tạo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td><strong>{{ $invoice->number }}</strong></td>
                            <td><span class="admin-branch-chip">{{ $invoice->branch?->code }}</span></td>
                            <td>
                                <strong>{{ $invoice->customer_name ?: 'Khách lẻ' }}</strong>
                                @if ($invoice->customer_phone)<p>{{ $invoice->customer_phone }}</p>@endif
                            </td>
                            <td>{{ $invoice->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="admin-numeric"><x-admin.money :value="$invoice->total" /></td>
                            <td><x-admin.status-badge :status="$invoice->status" /></td>
                            <td>{{ $invoice->payment_method?->label() ?? '—' }}</td>
                            <td>{{ $invoice->creator?->name ?? '—' }}</td>
                            <td><a href="{{ route('admin.invoices.edit', $invoice) }}">Xem chi tiết</a></td>
                        </tr>
                    @empty
                        <x-admin.empty-state
                            :colspan="9"
                            title="Chưa có hóa đơn nào"
                            hint="Hóa đơn được tạo từ màn hình lịch hẹn khi khách đã đến hoặc đã hoàn tất dịch vụ."
                        />
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$invoices" />
    </section>
</x-layouts.admin>
