<x-layouts.admin title="Nhà cung cấp" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <x-admin.page-header title="Nhà cung cấp" description="Nhà cung cấp đã phát sinh phiếu nhập sẽ được vô hiệu hóa thay vì xóa.">
        <x-slot:actions>
            <a href="{{ route('admin.suppliers.create') }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
                <x-admin.icon name="plus" size="18" /> Thêm nhà cung cấp
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <section class="card overflow-hidden">
        <x-admin.filter-bar :action="route('admin.suppliers.index')">
            <div class="col-12 col-lg-4">
                <label class="form-label" for="search">Tìm kiếm</label>
                <input class="form-control" id="search" type="search" name="search" value="{{ request('search') }}" placeholder="Tên hoặc số điện thoại">
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label" for="status">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tất cả</option>
                    <option value="active" @selected(request('status') === 'active')>Đang hợp tác</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Đã ngưng</option>
                </select>
            </div>
        </x-admin.filter-bar>

        <table class="table neo-table align-middle mb-0">
            <caption class="visually-hidden">Danh sách nhà cung cấp</caption>
            <thead>
                <tr>
                    <th scope="col">Nhà cung cấp</th>
                    <th scope="col">Liên hệ</th>
                    <th scope="col" class="text-end">Số phiếu</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col"><span class="visually-hidden">Thao tác</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suppliers as $supplier)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $supplier->name }}</span>
                            @if ($supplier->address)<small class="d-block text-body-secondary">{{ $supplier->address }}</small>@endif
                        </td>
                        <td data-label="Liên hệ">
                            <span class="neo-num">{{ $supplier->phone ?: '—' }}</span>
                            @if ($supplier->email)<small class="d-block text-body-secondary">{{ $supplier->email }}</small>@endif
                        </td>
                        <td data-label="Số phiếu" class="text-end neo-num">{{ $supplier->inventory_movements_count }}</td>
                        <td data-label="Trạng thái" class="neo-table__status">
                            <x-admin.status-badge :tone="$supplier->is_active ? 'is-success' : 'is-muted'" :label="$supplier->is_active ? 'Đang hợp tác' : 'Đã ngưng'" />
                        </td>
                        <td>
                            <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state :colspan="5" title="Chưa có nhà cung cấp" hint="Thêm nhà cung cấp để gắn vào phiếu nhập kho." />
                @endforelse
            </tbody>
        </table>

        <x-admin.pagination :paginator="$suppliers" />
    </section>
</x-layouts.admin>
