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
        <table class="table neo-table align-middle mb-0">
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
                        <td>
                            <span class="fw-semibold">{{ $branch->name }}</span>
                            <small class="d-block text-body-secondary">
                                <span class="badge rounded-pill text-bg-light border fw-normal">{{ $branch->code }}</span>
                                {{ $branch->address }}
                            </small>
                        </td>
                        <td data-label="Liên hệ" class="neo-num">{{ $branch->phone ?: '—' }}</td>
                        <td data-label="Nhân sự" class="text-end neo-num">{{ $branch->assignments_count }}</td>
                        <td data-label="Hóa đơn" class="text-end neo-num">{{ $branch->invoices_count }}</td>
                        <td data-label="Trạng thái" class="neo-table__status">
                            <x-admin.status-badge :tone="$branch->is_active ? 'is-success' : 'is-muted'" :label="$branch->is_active ? 'Đang hoạt động' : 'Đã ngừng'" />
                        </td>
                        <td>
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
