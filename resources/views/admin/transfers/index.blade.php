<x-layouts.admin title="Chuyển kho" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <x-admin.page-header title="Phiếu chuyển kho" description="Hoàn tất phiếu sẽ tạo một phiếu xuất tại chi nhánh gửi và một phiếu nhập tại chi nhánh nhận.">
        <x-slot:actions>
            @can('create', App\Models\StockTransfer::class)
                <a href="{{ route('admin.stock-transfers.create') }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
                    <x-admin.icon name="plus" size="18" /> Tạo phiếu chuyển
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <section class="card overflow-hidden">
        <x-admin.filter-bar :action="route('admin.stock-transfers.index')">
            <div class="col-12 col-lg-3">
                <label class="form-label" for="status">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tất cả</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-admin.filter-bar>

        <table class="table neo-table align-middle mb-0">
            <caption class="visually-hidden">Danh sách phiếu chuyển kho</caption>
            <thead>
                <tr>
                    <th scope="col">Số phiếu</th>
                    <th scope="col">Từ chi nhánh</th>
                    <th scope="col">Đến chi nhánh</th>
                    <th scope="col" class="text-end">Số dòng</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col">Người tạo</th>
                    <th scope="col"><span class="visually-hidden">Thao tác</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transfers as $transfer)
                    <tr>
                        <td>
                            <a href="{{ route('admin.stock-transfers.show', $transfer) }}" class="fw-semibold text-decoration-none neo-doc-no">{{ $transfer->number }}</a>
                            <small class="d-block text-body-secondary neo-num">{{ $transfer->created_at?->format('d/m/Y H:i') }}</small>
                        </td>
                        <td data-label="Từ chi nhánh"><span class="badge rounded-pill text-bg-light border fw-normal">{{ $transfer->sourceBranch?->code }}</span> {{ $transfer->sourceBranch?->name }}</td>
                        <td data-label="Đến chi nhánh"><span class="badge rounded-pill text-bg-light border fw-normal">{{ $transfer->destinationBranch?->code }}</span> {{ $transfer->destinationBranch?->name }}</td>
                        <td data-label="Số dòng" class="text-end neo-num">{{ $transfer->items_count }}</td>
                        <td data-label="Trạng thái"><x-admin.status-badge :status="$transfer->status" /></td>
                        <td data-label="Người tạo">{{ $transfer->creator?->name ?? '—' }}</td>
                        <td><a href="{{ route('admin.stock-transfers.show', $transfer) }}" class="btn btn-sm btn-outline-secondary">Chi tiết</a></td>
                    </tr>
                @empty
                    <x-admin.empty-state
                        :colspan="7"
                        icon="box"
                        title="Chưa có phiếu chuyển kho nào"
                        hint="Tạo phiếu để điều chuyển vật tư giữa hai cơ sở."
                    />
                @endforelse
            </tbody>
        </table>

        <x-admin.pagination :paginator="$transfers" />
    </section>
</x-layouts.admin>
