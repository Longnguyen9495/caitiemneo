<x-layouts.admin title="Chấm công" heading="Nhân sự & lương">
    @include('admin.partials.staff-nav')

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>Chấm công tháng {{ $month->format('m/Y') }}</h2>
                <p>Mỗi nhân viên có tối đa một bản ghi cho mỗi ca trong ngày.</p>
            </div>
        </header>

        <x-admin.filter-bar :action="route('admin.attendance.index')">
            <label>Tháng<input type="month" name="month" value="{{ $month->format('Y-m') }}"></label>
            <label>
                Nhân viên
                <select name="employee_id">
                    <option value="">Tất cả</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Trạng thái
                <select name="status">
                    <option value="">Tất cả</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </x-admin.filter-bar>

        @if ($summary->isNotEmpty())
            <div class="admin-summary-grid">
                @foreach ($summary as $row)
                    <div>
                        <p>{{ $row->employee_name }}</p>
                        <strong>{{ rtrim(rtrim((string) $row->shift_total, '0'), '.') }} ca</strong>
                        <p>{{ $row->shift_rows }} bản ghi trong tháng</p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Ngày</th><th>Nhân viên</th><th>Ca</th><th class="admin-numeric">Hệ số</th><th>Trạng thái</th><th>Giờ vào / ra</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($records as $item)
                        <tr>
                            <td><strong>{{ $item->work_date->format('d/m/Y') }}</strong></td>
                            <td>{{ $item->employee?->name }}</td>
                            <td>{{ $item->shift_name }}@if ($item->note)<p>{{ $item->note }}</p>@endif</td>
                            <td class="admin-numeric">{{ rtrim(rtrim((string) $item->shift_value, '0'), '.') }}</td>
                            <td><x-admin.status-badge :status="$item->status" /></td>
                            <td>{{ $item->checked_in_at?->format('H:i') ?? '—' }} / {{ $item->checked_out_at?->format('H:i') ?? '—' }}</td>
                            <td>
                                <div class="admin-row-actions">
                                    @can('update', $item)
                                        <a href="{{ route('admin.attendance.edit', $item) }}">Sửa</a>
                                    @endcan
                                    @can('delete', $item)
                                        <x-admin.confirm-form
                                            :action="route('admin.attendance.destroy', $item)"
                                            method="DELETE"
                                            label="Xóa"
                                            message="Xóa ca chấm công này?"
                                        />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty-state :colspan="7" title="Chưa có dữ liệu chấm công trong tháng này" />
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$records" />
    </section>

    @can('create', App\Models\AttendanceRecord::class)
        @include('admin.attendance.partials.quick-form')
    @endcan
</x-layouts.admin>
