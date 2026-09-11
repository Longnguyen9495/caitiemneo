<x-layouts.admin title="Nhập xuất kho" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <x-admin.page-header title="Lịch sử nhập xuất" description="Số lượng âm là xuất kho, số dương là nhập kho hoặc điều chỉnh tăng.">
        <x-slot:actions>
            <a href="{{ route('admin.reports.export.inventory', request()->query()) }}" class="btn btn-sm btn-outline-secondary">Xuất CSV</a>
            @can('create', App\Models\InventoryMovement::class)
                <a href="{{ route('admin.inventory.create') }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
                    <x-admin.icon name="plus" size="18" /> Tạo phiếu
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <section class="card overflow-hidden">
        <x-admin.filter-bar :action="route('admin.inventory.index')">
            <div class="col-12 col-lg-3">
                <label class="form-label" for="product_id">Vật tư</label>
                <select class="form-select" id="product_id" name="product_id">
                    <option value="">Tất cả</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label" for="supplier_id">Nhà cung cấp</label>
                <select class="form-select" id="supplier_id" name="supplier_id">
                    <option value="">Tất cả</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) request('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="type">Loại phiếu</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tất cả</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
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
            <caption class="visually-hidden">Danh sách phiếu kho</caption>
            <thead>
                <tr>
                    <th scope="col">Thời điểm</th>
                    <th scope="col">Vật tư</th>
                    <th scope="col">Loại</th>
                    <th scope="col" class="text-end">Thay đổi</th>
                    <th scope="col" class="text-end">Đơn giá</th>
                    <th scope="col">Nhà cung cấp</th>
                    <th scope="col">Tham chiếu</th>
                    <th scope="col">Người tạo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr>
                        <td>
                            <span class="fw-semibold neo-num">{{ $movement->occurred_at?->format('d/m/Y') }}</span>
                            <small class="text-body-secondary neo-num">{{ $movement->occurred_at?->format('H:i') }}</small>
                        </td>
                        <td data-label="Vật tư">
                            {{ $movement->product?->name }}
                            @if ($movement->note)<small class="d-block text-body-secondary">{{ $movement->note }}</small>@endif
                        </td>
                        <td data-label="Loại"><x-admin.status-badge :status="$movement->type" /></td>
                        <td data-label="Thay đổi" class="text-end">
                            <span class="fw-semibold neo-num {{ (float) $movement->quantity < 0 ? 'text-danger' : 'text-success' }}">
                                {{ (float) $movement->quantity > 0 ? '+' : '' }}{{ rtrim(rtrim((string) $movement->quantity, '0'), '.') }}
                            </span>
                            <small class="text-body-secondary">{{ $movement->product?->unit }}</small>
                        </td>
                        <td data-label="Đơn giá" class="text-end"><x-admin.money :value="$movement->unit_cost" /></td>
                        <td data-label="Nhà cung cấp">{{ $movement->supplier?->name ?? '—' }}</td>
                        <td data-label="Tham chiếu">{{ $movement->reference ?: '—' }}</td>
                        <td data-label="Người tạo">{{ $movement->creator?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <x-admin.empty-state
                        :colspan="8"
                        icon="box"
                        title="Chưa có phiếu kho nào"
                        hint="Tạo phiếu nhập đầu tiên để bắt đầu theo dõi tồn kho."
                    />
                @endforelse
            </tbody>
        </table>

        <x-admin.pagination :paginator="$movements" />
    </section>
</x-layouts.admin>
