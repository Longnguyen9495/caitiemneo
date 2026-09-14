<x-layouts.admin title="Dịch vụ" heading="Dịch vụ">
    <x-admin.page-header
        title="Danh mục dịch vụ"
        description="Giá ở đây là giá chung. Giá và thời lượng riêng của từng chi nhánh đặt trong mục Chi nhánh."
    >
        <x-slot:actions>
            @can('create', App\Models\Service::class)
                <a href="{{ route('admin.services.create') }}" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                    <x-admin.icon name="plus" size="18" /> Thêm dịch vụ
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <section class="card overflow-hidden">
        <x-admin.filter-bar :action="route('admin.services.index')">
            <div class="col-12 col-lg-4">
                <label class="form-label" for="search">Tìm kiếm</label>
                <input class="form-control" id="search" type="search" name="search" value="{{ request('search') }}" placeholder="Tên dịch vụ">
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label" for="category">Nhóm</label>
                <select class="form-select" id="category" name="category">
                    <option value="">Tất cả</option>
                    @foreach (App\Enums\ServiceCategory::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label" for="status">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tất cả</option>
                    <option value="active" @selected(request('status') === 'active')>Đang áp dụng</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Đã ẩn</option>
                </select>
            </div>
        </x-admin.filter-bar>

        <table class="table neo-table align-middle mb-0">
            <caption class="visually-hidden">Danh sách dịch vụ</caption>
            <thead>
                <tr>
                    <th scope="col">Dịch vụ</th>
                    <th scope="col">Nhóm</th>
                    <th scope="col" class="text-end">Giá niêm yết</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col"><span class="visually-hidden">Thao tác</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($services as $service)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $service->name }}</span>
                            @if ($service->description)<small class="d-block text-body-secondary">{{ $service->description }}</small>@endif
                        </td>
                        <td data-label="Nhóm">
                            <x-admin.status-badge :status="$service->category" />
                            <small class="d-block text-body-secondary">{{ $service->unit?->priceNote() }}</small>
                        </td>
                        <td data-label="Giá niêm yết" class="text-end neo-num neo-doc-no">
                            @if ($service->hasPriceRange())
                                {{ $service->formattedPriceRange() }}
                            @else
                                <x-admin.money :value="$service->price" />
                            @endif
                        </td>
                        <td data-label="Trạng thái" class="neo-table__status">
                            <x-admin.status-badge :tone="$service->is_active ? 'is-success' : 'is-muted'" :label="$service->is_active ? 'Đang áp dụng' : 'Đã ẩn'" />
                        </td>
                        <td>
                            @can('update', $service)
                                <a href="{{ route('admin.services.edit', $service) }}" class="btn btn-sm btn-outline-secondary">Chỉnh sửa</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state
                        :colspan="5"
                        title="Chưa có dịch vụ"
                        hint="Hãy thêm dịch vụ đầu tiên để nhận lịch đặt online."
                    />
                @endforelse
            </tbody>
        </table>

        <x-admin.pagination :paginator="$services" />
    </section>
</x-layouts.admin>
