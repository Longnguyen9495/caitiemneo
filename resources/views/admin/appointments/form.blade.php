<x-layouts.admin
    :title="$appointment->exists ? 'Sửa lịch hẹn' : 'Tạo lịch hẹn'"
    :heading="$appointment->exists ? 'Chỉnh sửa lịch hẹn' : 'Tạo lịch hẹn mới'"
>
    <section class="admin-form-panel">
        <header class="admin-form-header">
            <h2>{{ $appointment->exists ? 'Cập nhật thông tin lịch' : 'Thông tin khách và khung giờ' }}</h2>
            <p>
                Chi nhánh: <strong>{{ $branch?->name ?? 'Chưa chọn' }}</strong>.
                Hệ thống chặn khung giờ đã bị giữ bởi cùng một nhân viên, kể cả khi lịch kia thuộc chi nhánh khác.
            </p>
        </header>

        <form
            method="POST"
            action="{{ $appointment->exists
                ? route('admin.appointments.update', $appointment)
                : route('admin.appointments.store') }}"
            class="admin-form"
        >
            @csrf
            @if ($appointment->exists)
                @method('PUT')
            @endif

            @error('branch_id')<small class="admin-form-wide">{{ $message }}</small>@enderror

            <label>
                Tên khách hàng
                <input name="customer_name" value="{{ old('customer_name', $appointment->customer_name) }}" required autofocus>
                @error('customer_name')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Số điện thoại
                <input name="customer_phone" value="{{ old('customer_phone', $appointment->customer_phone) }}" required inputmode="tel">
                @error('customer_phone')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Nhân viên thực hiện
                <select name="employee_id">
                    <option value="">Tiệm sẽ sắp xếp sau</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id', $appointment->employee_id) === (string) $employee->id)>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
                @error('employee_id')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Thời gian bắt đầu
                <input
                    type="datetime-local"
                    name="starts_at"
                    value="{{ old('starts_at', $appointment->starts_at?->format('Y-m-d\TH:i')) }}"
                    required
                >
                @error('starts_at')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Thời lượng (phút)
                <input type="number" name="duration_minutes" min="15" max="480" step="15" value="{{ old('duration_minutes', $appointment->duration_minutes) }}" required>
                @error('duration_minutes')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Trạng thái
                <select name="status" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $appointment->status?->value ?? 'pending') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<small>{{ $message }}</small>@enderror
            </label>

            <fieldset class="admin-form-wide">
                <legend>Dịch vụ dự kiến</legend>
                <div class="admin-check-grid">
                    @forelse ($services as $branchService)
                        <label class="admin-checkbox">
                            <input
                                type="checkbox"
                                name="service_ids[]"
                                value="{{ $branchService->service_id }}"
                                @checked(in_array($branchService->service_id, old('service_ids', $appointment->services->pluck('service_id')->all())))
                            >
                            <span>{{ $branchService->service?->name }} <small>{{ \App\Support\Money::format($branchService->price) }} · {{ $branchService->duration_minutes }} phút</small></span>
                        </label>
                    @empty
                        <p class="admin-muted-text">Chi nhánh này chưa cấu hình dịch vụ nào.</p>
                    @endforelse
                </div>
                @error('service_ids')<small>{{ $message }}</small>@enderror
            </fieldset>

            <label class="admin-form-wide">
                Ghi chú
                <textarea name="note" rows="4" placeholder="Yêu cầu về màu, mẫu móng hoặc điều cần lưu ý…">{{ old('note', $appointment->note) }}</textarea>
                @error('note')<small>{{ $message }}</small>@enderror
            </label>

            <div class="admin-form-actions admin-form-wide">
                <a href="{{ route('admin.appointments.index', ['date' => $appointment->starts_at?->toDateString()]) }}">Hủy</a>
                <x-admin.submit-button :label="$appointment->exists ? 'Lưu thay đổi' : 'Tạo lịch hẹn'" />
            </div>
        </form>
    </section>
</x-layouts.admin>
