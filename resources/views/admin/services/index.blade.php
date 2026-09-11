<x-layouts.admin title="Dịch vụ" heading="Danh mục dịch vụ">
    <section class="admin-panel">
        <header class="admin-panel-header">
            <div><h2>Dịch vụ tại tiệm</h2><p>Quản lý mức giá và tình trạng hiển thị trong biểu mẫu đặt lịch.</p></div>
            @can('create', App\Models\Service::class)
                <a href="{{ route('admin.services.create') }}" class="admin-button">+ Thêm dịch vụ</a>
            @endcan
        </header>

        <x-admin.filter-bar :action="route('admin.services.index')">
            <label>Tìm kiếm<input type="search" name="search" value="{{ request('search') }}" placeholder="Tên dịch vụ"></label>
            <label>
                Trạng thái
                <select name="status">
                    <option value="">Tất cả</option>
                    <option value="active" @selected(request('status') === 'active')>Đang áp dụng</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Đã ẩn</option>
                </select>
            </label>
        </x-admin.filter-bar>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Dịch vụ</th><th class="admin-numeric">Giá niêm yết</th><th>Trạng thái</th><th></th></tr></thead>
                <tbody>
                    @forelse ($services as $service)
                        <tr>
                            <td><strong>{{ $service->name }}</strong>@if ($service->description)<p>{{ $service->description }}</p>@endif</td>
                            <td class="admin-numeric"><x-admin.money :value="$service->price" /></td>
                            <td><x-admin.status-badge :tone="$service->is_active ? 'is-active' : 'is-muted'" :label="$service->is_active ? 'Đang áp dụng' : 'Đã ẩn'" /></td>
                            <td>
                                @can('update', $service)
                                    <a href="{{ route('admin.services.edit', $service) }}">Chỉnh sửa</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty-state :colspan="4" title="Chưa có dịch vụ" hint="Hãy thêm dịch vụ đầu tiên để nhận lịch đặt online." />
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$services" />
    </section>
</x-layouts.admin>
