<x-layouts.admin
    :title="$appointment->exists ? 'Sửa lịch hẹn' : 'Tạo lịch hẹn'"
    :heading="$appointment->exists ? 'Sửa lịch hẹn' : 'Tạo lịch hẹn'"
>
    <x-admin.page-header
        :title="$appointment->exists ? 'Cập nhật lịch hẹn' : 'Lịch hẹn mới'"
        :description="'Chi nhánh '.($branch?->name ?? 'chưa chọn').'. Hệ thống chặn khung giờ nhân viên đã bận, kể cả ở chi nhánh khác.'"
        :breadcrumbs="['Lịch hẹn' => route('admin.appointments.index'), ($appointment->exists ? 'Chỉnh sửa' : 'Tạo mới') => null]"
    />

    <form method="POST" class="card p-3 p-lg-4"
          action="{{ $appointment->exists ? route('admin.appointments.update', $appointment) : route('admin.appointments.store') }}">
        @csrf
        @if ($appointment->exists)
            @method('PUT')
        @endif

        @error('branch_id')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

        <div class="row g-3">
            <x-admin.field name="customer_name" label="Tên khách hàng" required>
                <input class="form-control @error('customer_name') is-invalid @enderror" id="customer_name"
                       name="customer_name" value="{{ old('customer_name', $appointment->customer_name) }}" required autofocus>
            </x-admin.field>

            <x-admin.field name="customer_phone" label="Số điện thoại" required>
                <input class="form-control neo-num @error('customer_phone') is-invalid @enderror" id="customer_phone"
                       name="customer_phone" type="tel" inputmode="tel"
                       value="{{ old('customer_phone', $appointment->customer_phone) }}" required>
            </x-admin.field>

            <x-admin.field name="customer_email" label="Email khách hàng" help="Không bắt buộc; dùng để lưu thông tin liên hệ của khách.">
                <input class="form-control @error('customer_email') is-invalid @enderror" id="customer_email"
                       name="customer_email" type="email" autocomplete="email"
                       value="{{ old('customer_email', $appointment->customer_email) }}">
            </x-admin.field>

            <x-admin.field name="employee_id" label="Nhân viên thực hiện"
                           help="Chỉ hiện nhân viên được phân công tại chi nhánh này.">
                <select class="form-select @error('employee_id') is-invalid @enderror" id="employee_id" name="employee_id">
                    <option value="">Tiệm sẽ sắp xếp sau</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id', $appointment->employee_id) === (string) $employee->id)>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="status" label="Trạng thái" required>
                <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $appointment->status?->value ?? 'pending') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.field>

            <x-admin.field name="starts_at" label="Thời gian bắt đầu" required>
                <input class="form-control @error('starts_at') is-invalid @enderror" id="starts_at"
                       type="datetime-local" name="starts_at"
                       value="{{ old('starts_at', $appointment->starts_at?->format('Y-m-d\TH:i')) }}" required>
            </x-admin.field>

            <x-admin.field name="duration_minutes" label="Thời lượng (phút)" required>
                <input class="form-control neo-num @error('duration_minutes') is-invalid @enderror" id="duration_minutes"
                       type="number" name="duration_minutes" min="15" max="480" step="15" inputmode="numeric"
                       value="{{ old('duration_minutes', $appointment->duration_minutes) }}" required>
            </x-admin.field>

            <div class="col-12">
                <fieldset>
                    <legend class="form-label">Dịch vụ dự kiến</legend>
                    <div class="row g-2">
                        @forelse ($services as $branchService)
                            <div class="col-12 col-lg-6">
                                <label class="form-check card px-3 py-2 mb-0 d-flex flex-row align-items-center gap-2">
                                    <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="service_ids[]"
                                           value="{{ $branchService->service_id }}"
                                           @checked(in_array($branchService->service_id, old('service_ids', $appointment->services->pluck('service_id')->all())))>
                                    <span>
                                        <span class="d-block fw-medium">{{ $branchService->service?->name }}</span>
                                        <small class="text-body-secondary neo-num">{{ \App\Support\Money::format($branchService->price) }} · {{ $branchService->duration_minutes }} phút</small>
                                    </span>
                                </label>
                            </div>
                        @empty
                            <div class="col-12"><p class="text-body-secondary small mb-0">Chi nhánh này chưa cấu hình dịch vụ nào.</p></div>
                        @endforelse
                    </div>
                    @error('service_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </fieldset>
            </div>

            <x-admin.field name="note" label="Ghi chú" col="col-12">
                <textarea class="form-control @error('note') is-invalid @enderror" id="note" name="note" rows="3"
                          placeholder="Yêu cầu về màu, mẫu móng hoặc điều cần lưu ý…">{{ old('note', $appointment->note) }}</textarea>
            </x-admin.field>
        </div>

        <div class="neo-formbar">
            <a href="{{ route('admin.appointments.index', ['date' => $appointment->starts_at?->toDateString()]) }}" class="btn btn-light">Hủy</a>
            <x-admin.submit-button :label="$appointment->exists ? 'Lưu thay đổi' : 'Tạo lịch hẹn'" />
        </div>
    </form>
</x-layouts.admin>
