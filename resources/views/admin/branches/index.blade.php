<x-layouts.admin title="Chi nhánh" heading="Chi nhánh">
    <x-admin.page-header title="Danh sách chi nhánh" description="Chi nhánh đã phát sinh dữ liệu sẽ được ngừng hoạt động thay vì xóa.">
        <x-slot:actions>
            @can('create', App\Models\Branch::class)
                <a href="{{ route('admin.branches.create') }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
                    <x-admin.icon name="plus" size="18" /> Thêm chi nhánh
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <section class="card overflow-hidden">
        <table class="table neo-table neo-table--branches align-middle mb-0">
            <caption class="visually-hidden">Danh sách chi nhánh</caption>
            <thead>
                <tr>
                    <th scope="col">Chi nhánh</th>
                    <th scope="col">Liên hệ</th>
                    <th scope="col" class="text-end">Nhân sự</th>
                    <th scope="col" class="text-end">Hóa đơn</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col"><span class="visually-hidden">Thao tác</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($branches as $branch)
                    <tr>
                        <td class="branch-list__identity">
                            <div class="branch-list__name">{{ $branch->name }}</div>
                            <div class="branch-list__details">
                                <span class="badge rounded-pill text-bg-light border fw-normal">{{ $branch->code }}</span>
                                <span class="branch-list__address">{{ $branch->address }}</span>
                            </div>
                        </td>
                        <td data-label="Liên hệ" class="branch-list__contact neo-num">{{ $branch->phone ?: 'Chưa cập nhật' }}</td>
                        <td data-label="Nhân sự" class="branch-list__metric neo-num">{{ $branch->assignments_count }}</td>
                        <td data-label="Hóa đơn" class="branch-list__metric neo-num">{{ $branch->invoices_count }}</td>
                        <td data-label="Trạng thái" class="neo-table__status">
                            <x-admin.status-badge :tone="$branch->is_active ? 'is-success' : 'is-muted'" :label="$branch->is_active ? 'Đang hoạt động' : 'Đã ngừng'" />
                        </td>
                        <td data-label="Thao tác" class="branch-list__actions">
                            <span class="neo-actions">
                                @can('update', $branch)
                                    <a href="{{ route('admin.branches.edit', $branch) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                                @endcan
                                @can('configure', $branch)
                                    <a href="{{ route('admin.branches.catalog.edit', $branch) }}" class="btn btn-sm btn-outline-primary">Danh mục</a>
                                @endcan
                            </span>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state :colspan="6" icon="branch" title="Chưa có chi nhánh nào" />
                @endforelse
            </tbody>
        </table>

        <x-admin.pagination :paginator="$branches" />
    </section>
</x-layouts.admin>
