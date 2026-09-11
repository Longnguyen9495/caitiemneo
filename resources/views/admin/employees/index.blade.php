<x-layouts.admin title="Nhân sự" heading="Nhân sự">
    @include('admin.partials.staff-nav')

    <x-admin.page-header title="Danh sách nhân sự" description="Quyền thao tác và mức lương của từng tài khoản được cấu hình tại đây.">
        <x-slot:actions>
            @can('create', App\Models\User::class)
                <a href="{{ route('admin.employees.create') }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
                    <x-admin.icon name="plus" size="18" /> Thêm nhân sự
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <section class="card overflow-hidden">
        <x-admin.filter-bar :action="route('admin.employees.index')">
            <div class="col-12 col-lg-4">
                <label class="form-label" for="search">Tìm kiếm</label>
                <input class="form-control" id="search" type="search" name="search" value="{{ request('search') }}" placeholder="Tên, email hoặc SĐT">
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label" for="role">Vai trò</label>
                <select class="form-select" id="role" name="role">
                    <option value="">Tất cả</option>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label" for="status">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tất cả</option>
                    <option value="active" @selected(request('status') === 'active')>Đang làm</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Đã nghỉ</option>
                </select>
            </div>
        </x-admin.filter-bar>

        <table class="table neo-table align-middle mb-0">
            <caption class="visually-hidden">Danh sách nhân sự</caption>
            <thead>
                <tr>
                    <th scope="col">Nhân sự</th>
                    <th scope="col">Vai trò</th>
                    <th scope="col" class="text-end">Lương cứng</th>
                    <th scope="col" class="text-end">Đơn giá ca</th>
                    <th scope="col" class="text-end">Hoa hồng</th>
                    <th scope="col">Quyền</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col"><span class="visually-hidden">Thao tác</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $employee->name }}</span>
                            <small class="d-block text-body-secondary">{{ $employee->email }}@if ($employee->phone) · {{ $employee->phone }}@endif</small>
                        </td>
                        <td data-label="Vai trò">{{ $employee->role->label() }}</td>
                        <td data-label="Lương cứng" class="text-end"><x-admin.money :value="$employee->base_salary" /></td>
                        <td data-label="Đơn giá ca" class="text-end"><x-admin.money :value="$employee->shift_rate" /></td>
                        <td data-label="Hoa hồng" class="text-end neo-num">{{ rtrim(rtrim((string) $employee->commission_rate, '0'), '.') }}%</td>
                        <td data-label="Quyền">
                            <span class="d-inline-flex flex-wrap gap-1">
                                @if ($employee->can_manage_appointments)<span class="badge rounded-pill text-bg-light border fw-normal">Lịch hẹn</span>@endif
                                @if ($employee->can_create_invoices)<span class="badge rounded-pill text-bg-light border fw-normal">Hóa đơn</span>@endif
                                @if ($employee->can_manage_payroll)<span class="badge rounded-pill text-bg-light border fw-normal">Bảng lương</span>@endif
                            </span>
                        </td>
                        <td data-label="Trạng thái">
                            <x-admin.status-badge :tone="$employee->is_active ? 'is-success' : 'is-muted'" :label="$employee->is_active ? 'Đang làm' : 'Đã nghỉ'" />
                        </td>
                        <td>
                            @can('update', $employee)
                                <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state :colspan="8" icon="people" title="Không tìm thấy nhân sự nào" />
                @endforelse
            </tbody>
        </table>

        <x-admin.pagination :paginator="$employees" />
    </section>
</x-layouts.admin>
