<x-layouts.admin title="Danh mục ca" heading="Danh mục ca">
    @include('admin.partials.staff-nav')

    <x-admin.page-header
        title="Ca làm chuẩn"
        description="Giờ bắt đầu và kết thúc ở đây là căn cứ để hệ thống tự xác định đi trễ và thời gian vượt ca."
    >
        <x-slot:actions>
            @can('create', App\Models\WorkShift::class)
                <a href="{{ route('admin.work-shifts.create') }}" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                    <x-admin.icon name="plus" size="18" /> Thêm ca
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <section class="card overflow-hidden">
        <table class="table neo-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Tên ca</th>
                    <th scope="col">Khung giờ</th>
                    <th scope="col">Phạm vi</th>
                    <th scope="col" class="text-end">Hệ số</th>
                    <th scope="col" class="text-end">Ân hạn</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col"><span class="visually-hidden">Thao tác</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($shifts as $shift)
                    <tr>
                        <td class="fw-semibold">
                            {{ $shift->name }}
                            <small class="d-block text-body-secondary">{{ $shift->assignments_count }} lượt đã phân</small>
                        </td>
                        <td data-label="Khung giờ" class="neo-num neo-doc-no">{{ $shift->timeRangeLabel() }}</td>
                        <td data-label="Phạm vi">{{ $shift->branch?->name ?? 'Dùng chung mọi chi nhánh' }}</td>
                        <td data-label="Hệ số" class="text-end neo-num">{{ rtrim(rtrim((string) $shift->shift_value, '0'), '.') }}</td>
                        <td data-label="Ân hạn" class="text-end neo-num">{{ $shift->grace_minutes }} phút</td>
                        <td data-label="Trạng thái">
                            <x-admin.status-badge
                                :label="$shift->is_active ? 'Đang dùng' : 'Ngừng dùng'"
                                :tone="$shift->is_active ? 'is-success' : 'is-muted'"
                            />
                        </td>
                        <td>
                            @can('update', $shift)
                                <a href="{{ route('admin.work-shifts.edit', $shift) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state
                        :colspan="7"
                        icon="clock"
                        title="Chưa có ca làm nào"
                        hint="Hãy tạo các khung ca của tiệm, ví dụ 09:00–19:00, trước khi phân ca cho nhân viên."
                    />
                @endforelse
            </tbody>
        </table>

        <x-admin.pagination :paginator="$shifts" />
    </section>
</x-layouts.admin>
