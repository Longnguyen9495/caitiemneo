<x-layouts.admin title="Bảng lương" heading="Bảng lương">
    @include('admin.partials.staff-nav')

    <x-admin.page-header
        title="Bảng lương"
        description="Hoa hồng tính từ hóa đơn đã thanh toán trong kỳ, theo mốc thời điểm thanh toán."
    >
        <x-slot:actions>
            @can('create', App\Models\Payroll::class)
                <a href="{{ route('admin.reports.export.payrolls', request()->query()) }}" class="btn btn-sm btn-outline-secondary">Xuất CSV</a>
                <a href="{{ route('admin.payrolls.create') }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
                    <x-admin.icon name="plus" size="18" /> Tạo bảng lương
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <section class="card overflow-hidden">
        <x-admin.filter-bar :action="route('admin.payrolls.index')">
            @can('create', App\Models\Payroll::class)
                <div class="col-12 col-lg-3">
                    <label class="form-label" for="employee_id">Nhân viên</label>
                    <select class="form-select" id="employee_id" name="employee_id">
                        <option value="">Tất cả</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endcan
            <div class="col-6 col-lg-2">
                <label class="form-label" for="status">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tất cả</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="from">Từ ngày</label>
                <input class="form-control" id="from" type="date" name="from" value="{{ request('from') }}">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="to">Đến ngày</label>
                <input class="form-control" id="to" type="date" name="to" value="{{ request('to') }}">
            </div>
        </x-admin.filter-bar>

        <table class="table neo-table align-middle mb-0">
            <caption class="visually-hidden">Danh sách bảng lương</caption>
            <thead>
                <tr>
                    <th scope="col">Nhân viên</th>
                    <th scope="col">Kỳ lương</th>
                    <th scope="col" class="text-end">Lương cứng</th>
                    <th scope="col" class="text-end">Lương ca</th>
                    <th scope="col" class="text-end">Hoa hồng</th>
                    <th scope="col" class="text-end">Thực nhận</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col"><span class="visually-hidden">Thao tác</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payrolls as $payroll)
                    <tr>
                        <td>
                            <a href="{{ route('admin.payrolls.show', $payroll) }}" class="fw-semibold text-decoration-none">{{ $payroll->employee?->name }}</a>
                            @if ($payroll->allocations->isNotEmpty())
                                <small class="d-block text-body-secondary">{{ $payroll->allocations->map(fn ($row) => $row->branch?->code)->filter()->join(' · ') }}</small>
                            @endif
                        </td>
                        <td data-label="Kỳ lương" class="neo-num">{{ $payroll->period_start->format('d/m/Y') }} — {{ $payroll->period_end->format('d/m/Y') }}</td>
                        <td data-label="Lương cứng" class="text-end"><x-admin.money :value="$payroll->base_salary" /></td>
                        <td data-label="Lương ca" class="text-end"><x-admin.money :value="$payroll->shift_pay" /></td>
                        <td data-label="Hoa hồng" class="text-end"><x-admin.money :value="$payroll->commission_pay" /></td>
                        <td data-label="Thực nhận" class="text-end fw-bold"><x-admin.money :value="$payroll->total" /></td>
                        <td data-label="Trạng thái" class="neo-table__status"><x-admin.status-badge :status="$payroll->status" /></td>
                        <td>
                            <a href="{{ route('admin.payrolls.show', $payroll) }}" class="btn btn-sm btn-outline-secondary">Chi tiết</a>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state
                        :colspan="8"
                        icon="wallet"
                        title="Chưa có bảng lương nào"
                        hint="Tạo bảng lương theo kỳ để tổng hợp lương cứng, lương ca và hoa hồng."
                    />
                @endforelse
            </tbody>
        </table>

        <x-admin.pagination :paginator="$payrolls" />
    </section>
</x-layouts.admin>
