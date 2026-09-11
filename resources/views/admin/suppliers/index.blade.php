<x-layouts.admin title="Nhà cung cấp" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>Nhà cung cấp</h2>
                <p>Nhà cung cấp đã phát sinh phiếu nhập sẽ được vô hiệu hóa thay vì xóa.</p>
            </div>
            <a href="{{ route('admin.suppliers.create') }}" class="admin-button">+ Thêm nhà cung cấp</a>
        </header>

        <x-admin.filter-bar :action="route('admin.suppliers.index')">
            <label>Tìm kiếm<input type="search" name="search" value="{{ request('search') }}" placeholder="Tên hoặc số điện thoại"></label>
            <label>
                Trạng thái
                <select name="status">
                    <option value="">Tất cả</option>
                    <option value="active" @selected(request('status') === 'active')>Đang hợp tác</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Đã ngưng</option>
                </select>
            </label>
        </x-admin.filter-bar>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Nhà cung cấp</th><th>Liên hệ</th><th class="admin-numeric">Số phiếu</th><th>Trạng thái</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td><strong>{{ $supplier->name }}</strong>@if ($supplier->address)<p>{{ $supplier->address }}</p>@endif</td>
                            <td>{{ $supplier->phone ?: '—' }}@if ($supplier->email)<p>{{ $supplier->email }}</p>@endif</td>
                            <td class="admin-numeric">{{ $supplier->inventory_movements_count }}</td>
                            <td><x-admin.status-badge :tone="$supplier->is_active ? 'is-active' : 'is-muted'" :label="$supplier->is_active ? 'Đang hợp tác' : 'Đã ngưng'" /></td>
                            <td><a href="{{ route('admin.suppliers.edit', $supplier) }}">Chỉnh sửa</a></td>
                        </tr>
                    @empty
                        <x-admin.empty-state :colspan="5" title="Chưa có nhà cung cấp" hint="Thêm nhà cung cấp để gắn vào phiếu nhập kho." />
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$suppliers" />
    </section>
</x-layouts.admin>
