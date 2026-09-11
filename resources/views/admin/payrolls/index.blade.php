<x-layouts.admin title="Bảng lương" heading="Nhân sự & lương">
    @include('admin.partials.staff-nav')

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>Bảng lương</h2>
                <p>Hoa hồng được tính từ các hóa đơn đã thanh toán trong kỳ, theo mốc thời điểm thanh toán.</p>
            </div>
            <div class="admin-page-actions">
                @can('create', App\Models\Payroll::class)
                    <a href="{{ route('admin.reports.export.payrolls', request()->query()) }}" class="admin-button is-ghost">Xuất CSV</a>
                    <a href="{{ route('admin.payrolls.create') }}" class="admin-button">+ Tạo bảng lương</a>
                @endcan
            </div>
        </header>

        <x-admin.filter-bar :action="route('admin.payrolls.index')">
            @can('create', App\Models\Payroll::class)
                <label>
                    Nhân viên
                    <select name="employee_id">
                        <option value="">Tất cả</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endcan
            <label>
                Trạng thái
                <select name="status">
                    <option value="">Tất cả</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Từ ngày<input type="date" name="from" value="{{ request('from') }}"></label>
            <label>Đến ngày<input type="date" name="to" value="{{ request('to') }}"></label>
        </x-admin.filter-bar>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nhân viên</th>
                        <th>Kỳ lương</th>
                        <th class="admin-numeric">Lương cứng</th>
                        <th class="admin-numeric">Lương ca</th>
                        <th class="admin-numeric">Hoa hồng</th>
                        <th class="admin-numeric">Thực nhận</th>
                        <th>Trạng thái</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payrolls as $payroll)
                        <tr>
                            <td><strong>{{ $payroll->employee?->name }}</strong></td>
                            <td>{{ $payroll->period_start->format('d/m/Y') }} — {{ $payroll->period_end->format('d/m/Y') }}</td>
                            <td class="admin-numeric"><x-admin.money :value="$payroll->base_salary" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$payroll->shift_pay" /></td>
                            <td class="admin-numeric"><x-admin.money :value="$payroll->commission_pay" /></td>
                            <td class="admin-numeric"><strong><x-admin.money :value="$payroll->total" /></strong></td>
                            <td><x-admin.status-badge :status="$payroll->status" /></td>
                            <td><a href="{{ route('admin.payrolls.show', $payroll) }}">Xem chi tiết</a></td>
                        </tr>
                    @empty
                        <x-admin.empty-state :colspan="8" title="Chưa có bảng lương nào" hint="Tạo bảng lương theo kỳ để tổng hợp lương cứng, lương ca và hoa hồng." />
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$payrolls" />
    </section>
</x-layouts.admin>
