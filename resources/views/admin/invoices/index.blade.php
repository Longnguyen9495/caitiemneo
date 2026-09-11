<x-layouts.admin title="Hóa đơn" heading="Hóa đơn & thu chi">
    @include('admin.partials.finance-nav')

    <x-admin.page-header title="Hóa đơn" description="Doanh thu ghi nhận theo thời điểm thanh toán.">
        <x-slot:actions>
            <a href="{{ route('admin.reports.export.invoices', request()->query()) }}" class="btn btn-sm btn-outline-secondary">Xuất CSV</a>
        </x-slot:actions>
    </x-admin.page-header>

    <section class="card overflow-hidden">
        <x-admin.filter-bar :action="route('admin.invoices.index')">
            <div class="col-12 col-lg-3">
                <label class="form-label" for="search">Tìm kiếm</label>
                <input class="form-control" id="search" type="search" name="search" value="{{ request('search') }}" placeholder="Số hóa đơn, tên hoặc SĐT">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="status">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tất cả</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="payment_method">Phương thức</label>
                <select class="form-select" id="payment_method" name="payment_method">
                    <option value="">Tất cả</option>
                    @foreach ($paymentMethods as $value => $label)
                        <option value="{{ $value }}" @selected(request('payment_method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="from">Từ ngày</label>
                <input class="form-control" id="from" type="date" name="from" value="{{ request('from') }}">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="to">Đến ngày</label>
                <input class="form-control" id="to" type="date" name="to" value="{{ request('to') }}">
            </div>
        </x-admin.filter-bar>

        <table class="table neo-table align-middle mb-0">
            <caption class="visually-hidden">Danh sách hóa đơn</caption>
            <thead>
                <tr>
                    <th scope="col">Số hóa đơn</th>
                    <th scope="col">Chi nhánh</th>
                    <th scope="col">Khách hàng</th>
                    <th scope="col">Ngày tạo</th>
                    <th scope="col" class="text-end">Tổng tiền</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col">Phương thức</th>
                    <th scope="col">Người tạo</th>
                    <th scope="col"><span class="visually-hidden">Thao tác</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td>
                            <a href="{{ route('admin.invoices.edit', $invoice) }}" class="fw-semibold text-decoration-none neo-doc-no">{{ $invoice->number }}</a>
                        </td>
                        <td data-label="Chi nhánh"><span class="badge rounded-pill text-bg-light border fw-normal">{{ $invoice->branch?->code }}</span></td>
                        <td data-label="Khách hàng">
                            {{ $invoice->customer_name ?: 'Khách lẻ' }}
                            @if ($invoice->customer_phone)<small class="d-block text-body-secondary neo-num">{{ $invoice->customer_phone }}</small>@endif
                        </td>
                        <td data-label="Ngày tạo" class="neo-num">{{ $invoice->created_at?->format('d/m/Y H:i') }}</td>
                        <td data-label="Tổng tiền" class="text-end fw-semibold"><x-admin.money :value="$invoice->total" /></td>
                        <td data-label="Trạng thái"><x-admin.status-badge :status="$invoice->status" /></td>
                        <td data-label="Phương thức">{{ $invoice->payment_method?->label() ?? '—' }}</td>
                        <td data-label="Người tạo">{{ $invoice->creator?->name ?? '—' }}</td>
                        <td>
                            <a href="{{ route('admin.invoices.edit', $invoice) }}" class="btn btn-sm btn-outline-secondary">Chi tiết</a>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state
                        :colspan="9"
                        icon="receipt"
                        title="Chưa có hóa đơn nào"
                        hint="Hóa đơn được tạo từ màn hình lịch hẹn khi khách đã đến hoặc đã hoàn tất dịch vụ."
                    />
                @endforelse
            </tbody>
        </table>

        <x-admin.pagination :paginator="$invoices" />
    </section>
</x-layouts.admin>
