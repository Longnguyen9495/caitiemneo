<x-layouts.admin title="Chấm công" heading="Chấm công">
    @include('admin.partials.staff-nav')

    <x-admin.page-header
        :title="'Tháng '.$month->format('m/Y')"
        description="Ca thường do nhân viên tự chấm bằng GPS. Biểu mẫu dưới đây dành cho ngoại lệ và luôn ghi lý do vào nhật ký."
    >
        <x-slot:actions>
            @if ($pendingOvertimeCount > 0)
                <a href="{{ route('admin.attendance.review') }}" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                    Tăng ca chờ duyệt
                    <span class="badge rounded-pill text-bg-warning">{{ $pendingOvertimeCount }}</span>
                </a>
            @endif
            @can('create', App\Models\AttendanceRecord::class)
                <button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" type="button"
                        data-bs-toggle="collapse" data-bs-target="#quickShift" aria-controls="quickShift">
                    <x-admin.icon name="plus" size="18" /> Ghi ca làm
                </button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    @can('create', App\Models\AttendanceRecord::class)
        {{-- Form ghi ca đặt ngay đầu trang và mở sẵn khi có lỗi: đây là việc
             làm nhiều lần mỗi ngày, không nên bắt cuộn xuống cuối. --}}
        <div @class(['collapse', 'mb-3', 'show' => $errors->any()]) id="quickShift">
            @include('admin.attendance.partials.quick-form')
        </div>
    @endcan

    @if ($summary->isNotEmpty())
        <div class="row g-2 mb-3">
            @foreach ($summary as $row)
                <div class="col-6 col-lg-3">
                    <dl class="neo-stat mb-0">
                        <dt class="text-truncate">{{ $row->employee_name }}</dt>
                        <dd class="fs-6">{{ rtrim(rtrim((string) $row->shift_total, '0'), '.') }} ca</dd>
                        <small>{{ $row->shift_rows }} bản ghi</small>
                    </dl>
                </div>
            @endforeach
        </div>
    @endif

    <section class="card overflow-hidden">
        <x-admin.filter-bar :action="route('admin.attendance.index')">
            <div class="col-12 col-lg-3">
                <label class="form-label" for="month">Tháng</label>
                <input class="form-control" id="month" type="month" name="month" value="{{ $month->format('Y-m') }}">
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label" for="employee_id">Nhân viên</label>
                <select class="form-select" id="employee_id" name="employee_id">
                    <option value="">Tất cả</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label" for="status">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tất cả</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label" for="source">Nguồn</label>
                <select class="form-select" id="source" name="source">
                    <option value="">Tất cả</option>
                    @foreach ($sources as $value => $label)
                        <option value="{{ $value }}" @selected(request('source') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-admin.filter-bar>

        <table class="table neo-table align-middle mb-0">
            <caption class="visually-hidden">Danh sách chấm công theo tháng đang chọn</caption>
            <thead>
                <tr>
                    <th scope="col">Ngày</th>
                    <th scope="col">Nhân viên</th>
                    <th scope="col">Ca</th>
                    <th scope="col" class="text-end">Hệ số</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col">Giờ vào / ra</th>
                    <th scope="col">Trễ / tăng ca</th>
                    <th scope="col"><span class="visually-hidden">Thao tác</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $item)
                    <tr>
                        <td class="neo-num fw-semibold">{{ $item->work_date->format('d/m/Y') }}</td>
                        <td data-label="Nhân viên">{{ $item->employee?->name }}</td>
                        <td data-label="Ca">
                            {{ $item->shift_name }}
                            @if ($item->note)<small class="d-block text-body-secondary">{{ $item->note }}</small>@endif
                        </td>
                        <td data-label="Hệ số" class="text-end neo-num">{{ rtrim(rtrim((string) $item->shift_value, '0'), '.') }}</td>
                        <td data-label="Trạng thái"><x-admin.status-badge :status="$item->status" /></td>
                        <td data-label="Giờ vào / ra" class="neo-num">{{ $item->checked_in_at?->format('H:i') ?? '—' }} / {{ $item->checked_out_at?->format('H:i') ?? '—' }}</td>
                        <td data-label="Trễ / tăng ca" class="neo-num">
                            {{ $item->late_minutes > 0 ? $item->late_minutes.'p trễ' : '—' }}
                            @if ($item->overtime_minutes > 0)
                                <span class="d-block">{{ $item->overtime_minutes }}p tăng ca</span>
                                <x-admin.status-badge :status="$item->overtime_status" />
                            @endif
                        </td>
                        <td>
                            <span class="d-inline-flex flex-wrap gap-2">
                                @can('update', $item)
                                    <a href="{{ route('admin.attendance.edit', $item) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                                @endcan
                                @can('delete', $item)
                                    <x-admin.confirm-form
                                        :action="route('admin.attendance.destroy', $item)"
                                        method="DELETE"
                                        label="Xóa"
                                        message="Xóa ca chấm công này?"
                                    />
                                @endcan
                            </span>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state
                        :colspan="8"
                        icon="clock"
                        title="Chưa có dữ liệu chấm công trong tháng này"
                        hint="Dùng nút Ghi ca làm ở trên để thêm ca đầu tiên."
                    />
                @endforelse
            </tbody>
        </table>

        <x-admin.pagination :paginator="$records" />
    </section>
</x-layouts.admin>
