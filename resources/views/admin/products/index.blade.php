<x-layouts.admin title="Vật tư" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <x-admin.page-header
        title="Danh mục vật tư"
        :description="'Tồn kho đang xem: '.$branchLabel.'. Tồn là số dư cộng dồn của mọi phiếu nhập, xuất và điều chỉnh.'"
    >
        <x-slot:actions>
            @can('create', App\Models\Product::class)
                <a href="{{ route('admin.products.create') }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
                    <x-admin.icon name="plus" size="18" /> Thêm vật tư
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <section class="card overflow-hidden">
        <x-admin.filter-bar :action="route('admin.products.index')">
            <div class="col-12 col-lg-4">
                <label class="form-label" for="search">Tìm kiếm</label>
                <input class="form-control" id="search" type="search" name="search" value="{{ request('search') }}" placeholder="Tên hoặc mã SKU">
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label" for="status">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tất cả</option>
                    <option value="active" @selected(request('status') === 'active')>Đang dùng</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Đã ngưng</option>
                </select>
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-check d-inline-flex align-items-center gap-2 mb-0 mt-lg-4">
                    <input class="form-check-input m-0" type="checkbox" name="low_stock" value="1" @checked(request()->boolean('low_stock'))>
                    <span class="small">Chỉ hiện tồn thấp</span>
                </label>
            </div>
        </x-admin.filter-bar>

        <table class="table neo-table align-middle mb-0">
            <caption class="visually-hidden">Danh sách vật tư</caption>
            <thead>
                <tr>
                    <th scope="col">Vật tư</th>
                    <th scope="col">Đơn vị</th>
                    <th scope="col" class="text-end">Giá vốn</th>
                    <th scope="col" class="text-end">Tồn hiện tại</th>
                    <th scope="col" class="text-end">Định mức</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col"><span class="visually-hidden">Thao tác</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    @php $isLow = (float) $product->current_stock <= (float) $product->effective_minimum_stock; @endphp
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $product->name }}</span>
                            @if ($product->sku)<small class="d-block text-body-secondary">SKU {{ $product->sku }}</small>@endif
                        </td>
                        <td data-label="Đơn vị">{{ $product->unit }}</td>
                        <td data-label="Giá vốn" class="text-end"><x-admin.money :value="$product->cost_price" /></td>
                        <td data-label="Tồn hiện tại" class="text-end">
                            <span class="fw-semibold neo-num">{{ rtrim(rtrim($product->current_stock, '0'), '.') }}</span>
                            @if ($isLow)<x-admin.status-badge tone="is-warning" label="Tồn thấp" class="ms-1" />@endif
                        </td>
                        <td data-label="Định mức" class="text-end neo-num">{{ rtrim(rtrim((string) $product->effective_minimum_stock, '0'), '.') }}</td>
                        <td data-label="Trạng thái" class="neo-table__status">
                            <x-admin.status-badge :tone="$product->is_active ? 'is-success' : 'is-muted'" :label="$product->is_active ? 'Đang dùng' : 'Đã ngưng'" />
                        </td>
                        <td>
                            <span class="d-inline-flex flex-wrap gap-2">
                                @can('update', $product)
                                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                                @endcan
                                @can('create', App\Models\InventoryMovement::class)
                                    <a href="{{ route('admin.inventory.create', ['product_id' => $product->id]) }}" class="btn btn-sm btn-outline-primary">Tạo phiếu</a>
                                @endcan
                            </span>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state
                        :colspan="7"
                        icon="box"
                        title="Chưa có vật tư nào"
                        hint="Thêm vật tư để bắt đầu theo dõi tồn kho và chi phí nhập hàng."
                    />
                @endforelse
            </tbody>
        </table>

        <x-admin.pagination :paginator="$products" />
    </section>
</x-layouts.admin>
