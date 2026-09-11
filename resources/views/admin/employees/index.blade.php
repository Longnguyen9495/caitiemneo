<x-layouts.admin title="Nhân sự" heading="Nhân sự & lương">
    @include('admin.partials.staff-nav')

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>Danh sách nhân sự</h2>
                <p>Quyền thao tác và mức lương của từng tài khoản được cấu hình tại đây.</p>
            </div>
            @can('create', App\Models\User::class)
                <a href="{{ route('admin.employees.create') }}" class="admin-button">+ Thêm nhân sự</a>
            @endcan
        </header>

        <x-admin.filter-bar :action="route('admin.employees.index')">
            <label>Tìm kiếm<input type="search" name="search" value="{{ request('search') }}" placeholder="Tên, email hoặc SĐT"></label>
            <label>
                Vai trò
                <select name="role">
                    <option value="">Tất cả</option>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Trạng thái
                <select name="status">
                    <option value="">Tất cả</option>
                    <option value="active" @selected(request('status') === 'active')>Đang làm</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Đã nghỉ</option>
                </select>
            </label>
        </x-admin.filter-bar>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nhân sự</th>
                        <th>Vai trò</th>
                        <th class="admin-numeric">Lương cứng</th>
                        <th class="admin-numeric">Đơn giá ca</th>
                        <th class="admin-numeric">Hoa hồng</th>
                        <th>Quyền</th>
                        <th>Trạng thái</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>
                                <strong>{{ $employee->name }}</strong>
                                <p>{{ $employee->email }}@if ($employee->phone) · {{ $employee->phone }}@endif</p>
                            </td>
                            <td>{{ $employee->role->label() }}</td>
                            <td class="admin-numeric"><x-admin.money :value="$employee->base_salary" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$employee->shift_rate" /></td>
                            <td class="admin-numeric">{{ rtrim(rtrim((string) $employee->commission_rate, '0'), '.') }}%</td>
                            <td>
                                @if ($employee->can_manage_appointments)<span class="admin-service-chip">Lịch hẹn</span>@endif
                                @if ($employee->can_create_invoices)<span class="admin-service-chip">Hóa đơn</span>@endif
                                @if ($employee->can_manage_payroll)<span class="admin-service-chip">Bảng lương</span>@endif
                            </td>
                            <td><x-admin.status-badge :tone="$employee->is_active ? 'is-success' : 'is-muted'" :label="$employee->is_active ? 'Đang làm' : 'Đã nghỉ'" /></td>
                            <td>
                                @can('update', $employee)
                                    <a href="{{ route('admin.employees.edit', $employee) }}">Chỉnh sửa</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty-state :colspan="8" title="Không tìm thấy nhân sự nào" />
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$employees" />
    </section>
</x-layouts.admin>
