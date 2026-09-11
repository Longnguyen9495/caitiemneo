<x-layouts.admin title="Chi nhánh" heading="Chi nhánh">
    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>Danh sách chi nhánh</h2>
                <p>Chi nhánh đã phát sinh dữ liệu sẽ được ngừng hoạt động thay vì xóa.</p>
            </div>
            @can('create', App\Models\Branch::class)
                <a href="{{ route('admin.branches.create') }}" class="admin-button">+ Thêm chi nhánh</a>
            @endcan
        </header>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Chi nhánh</th><th>Liên hệ</th><th class="admin-numeric">Nhân sự</th><th class="admin-numeric">Hóa đơn</th><th>Trạng thái</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <td>
                                <strong>{{ $branch->name }}</strong>
                                <p><span class="admin-branch-chip">{{ $branch->code }}</span> {{ $branch->address }}</p>
                            </td>
                            <td>{{ $branch->phone ?: '—' }}</td>
                            <td class="admin-numeric">{{ $branch->assignments_count }}</td>
                            <td class="admin-numeric">{{ $branch->invoices_count }}</td>
                            <td><x-admin.status-badge :tone="$branch->is_active ? 'is-success' : 'is-muted'" :label="$branch->is_active ? 'Đang hoạt động' : 'Đã ngừng'" /></td>
                            <td>
                                <div class="admin-row-actions">
                                    @can('update', $branch)
                                        <a href="{{ route('admin.branches.edit', $branch) }}">Chỉnh sửa</a>
                                    @endcan
                                    @can('configure', $branch)
                                        <a href="{{ route('admin.branches.catalog.edit', $branch) }}">Danh mục</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty-state :colspan="6" title="Chưa có chi nhánh nào" />
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$branches" />
    </section>
</x-layouts.admin>
