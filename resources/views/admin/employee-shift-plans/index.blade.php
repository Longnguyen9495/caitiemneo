<x-layouts.admin title="Ca cố định" heading="Ca cố định">
    @include('admin.partials.staff-nav')

    <x-admin.page-header
        title="Kế hoạch nhân sự tháng {{ $month->format('m/Y') }}"
        description="Ca cố định được dùng để tạo lịch tháng. Ngày nghỉ hưởng lương được xác định tự động khi quản lý duyệt đơn nghỉ của nhân viên."
    >
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.employee-shift-plans.index') }}" class="d-flex gap-2">
                <label class="visually-hidden" for="month">Tháng</label>
                <input class="form-control form-control-sm neo-num" id="month" name="month" type="month" value="{{ $month->format('Y-m') }}">
                <button class="btn btn-outline-secondary btn-sm" type="submit">Xem tháng</button>
            </form>
        </x-slot:actions>
    </x-admin.page-header>

    @if (! $branch)
        <x-admin.empty-state title="Hãy chọn một chi nhánh cụ thể" hint="Không thể cấu hình hoặc tạo lịch khi đang xem tất cả chi nhánh." />
    @elseif ($managesPlans)
        <div class="row g-3 mb-3">
            <section class="col-12 col-xl-6">
                <div class="card p-3 h-100">
                    <h2 class="fs-6 fw-semibold mb-1">Tạo lịch từ ca cố định</h2>
                    <p class="small text-body-secondary mb-3">Chỉ thêm ngày chưa có phân ca; không ghi đè lịch đã chấm công hoặc lịch được tạo thủ công.</p>
                    <form method="POST" action="{{ route('admin.employee-shift-plans.generate') }}" class="d-flex flex-wrap gap-2 align-items-end">
                        @csrf
                        <input name="month" type="hidden" value="{{ $month->format('Y-m') }}">
                        <x-admin.submit-button label="Tạo lịch tháng" />
                    </form>
                </div>
            </section>

            <section class="col-12 col-xl-6">
                <div class="card p-3 h-100">
                    <h2 class="fs-6 fw-semibold mb-3">Thêm ca cố định</h2>
                    <form method="POST" action="{{ route('admin.employee-shift-plans.fixed-shifts.store') }}" class="row g-2 align-items-end">
                        @csrf
                        <x-admin.field name="employee_id" label="Nhân viên" col="col-12 col-md-6" required>
                            <select class="form-select @error('employee_id') is-invalid @enderror" id="employee_id" name="employee_id" required>
                                <option value="">Chọn nhân viên</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->name }}</option>
                                @endforeach
                            </select>
                        </x-admin.field>
                        <x-admin.field name="work_shift_id" label="Ca làm" col="col-12 col-md-6" required>
                            <select class="form-select @error('work_shift_id') is-invalid @enderror" id="work_shift_id" name="work_shift_id" required>
                                <option value="">Chọn ca</option>
                                @foreach ($shifts as $shift)
                                    <option value="{{ $shift->id }}" @selected((string) old('work_shift_id') === (string) $shift->id)>{{ $shift->label() }}</option>
                                @endforeach
                            </select>
                        </x-admin.field>
                        <x-admin.field name="effective_from" label="Hiệu lực từ" col="col-12 col-md-5" required>
                            <input class="form-control neo-num @error('effective_from') is-invalid @enderror" id="effective_from" name="effective_from" type="date" required value="{{ old('effective_from', $month->toDateString()) }}">
                        </x-admin.field>
                        <x-admin.field name="effective_to" label="Kết thúc" col="col-12 col-md-5">
                            <input class="form-control neo-num @error('effective_to') is-invalid @enderror" id="effective_to" name="effective_to" type="date" value="{{ old('effective_to') }}">
                        </x-admin.field>
                        <div class="col-12 col-md-2"><x-admin.submit-button label="Lưu" class="w-100" /></div>
                    </form>
                </div>
            </section>
        </div>

    @endif

    <div class="row g-3">
        <section class="col-12">
            <div class="card overflow-hidden h-100">
                <div class="card-header bg-white"><h2 class="fs-6 fw-semibold mb-0">Ca cố định đang hiệu lực</h2></div>
                <table class="table neo-table align-middle mb-0">
                    <thead><tr><th>Nhân viên</th><th>Ca</th><th>Khoảng hiệu lực</th></tr></thead>
                    <tbody>
                        @forelse ($fixedShifts as $fixedShift)
                            <tr>
                                <td>{{ $fixedShift->employee?->name }}</td>
                                <td>{{ $fixedShift->workShift?->label() }}</td>
                                <td class="neo-num">{{ $fixedShift->effective_from->format('d/m/Y') }} – {{ $fixedShift->effective_to?->format('d/m/Y') ?? 'Không thời hạn' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-body-secondary py-4">Chưa có ca cố định phù hợp tháng này.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</x-layouts.admin>
