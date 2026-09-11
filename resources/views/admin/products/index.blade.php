<x-layouts.admin title="Vật tư" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>Danh mục vật tư</h2>
                <p>Tồn kho là số dư cộng dồn của toàn bộ phiếu nhập, xuất và điều chỉnh.</p>
            </div>
            @can('create', App\Models\Product::class)
                <a href="{{ route('admin.products.create') }}" class="admin-button">+ Thêm vật tư</a>
            @endcan
        </header>

        <x-admin.filter-bar :action="route('admin.products.index')">
            <label>Tìm kiếm<input type="search" name="search" value="{{ request('search') }}" placeholder="Tên hoặc mã SKU"></label>
            <label>
                Trạng thái
                <select name="status">
                    <option value="">Tất cả</option>
                    <option value="active" @selected(request('status') === 'active')>Đang dùng</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Đã ngưng</option>
                </select>
            </label>
            <label class="admin-checkbox"><input type="checkbox" name="low_stock" value="1" @checked(request()->boolean('low_stock'))><span>Chỉ hiện tồn thấp</span></label>
        </x-admin.filter-bar>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Vật tư</th>
                        <th>Đơn vị</th>
                        <th class="admin-numeric">Giá vốn</th>
                        <th class="admin-numeric">Tồn hiện tại</th>
                        <th class="admin-numeric">Định mức</th>
                        <th>Trạng thái</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        @php $isLow = (float) $product->current_stock <= (float) $product->minimum_stock; @endphp
                        <tr>
                            <td>
                                <strong>{{ $product->name }}</strong>
                                @if ($product->sku)<p>SKU {{ $product->sku }}</p>@endif
                            </td>
                            <td>{{ $product->unit }}</td>
                            <td class="admin-numeric"><x-admin.money :value="$product->cost_price" /></td>
                            <td class="admin-numeric">
                                {{ rtrim(rtrim($product->current_stock, '0'), '.') }}
                                @if ($isLow)<x-admin.status-badge tone="is-warning" label="Tồn thấp" />@endif
                            </td>
                            <td class="admin-numeric">{{ rtrim(rtrim((string) $product->minimum_stock, '0'), '.') }}</td>
                            <td><x-admin.status-badge :tone="$product->is_active ? 'is-active' : 'is-muted'" :label="$product->is_active ? 'Đang dùng' : 'Đã ngưng'" /></td>
                            <td>
                                <div class="admin-row-actions">
                                    @can('update', $product)
                                        <a href="{{ route('admin.products.edit', $product) }}">Chỉnh sửa</a>
                                    @endcan
                                    @can('create', App\Models\InventoryMovement::class)
                                        <a href="{{ route('admin.inventory.create', ['product_id' => $product->id]) }}">Tạo phiếu</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty-state :colspan="7" title="Chưa có vật tư nào" hint="Thêm vật tư để bắt đầu theo dõi tồn kho và chi phí nhập hàng." />
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$products" />
    </section>
</x-layouts.admin>
