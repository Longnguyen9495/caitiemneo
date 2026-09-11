<x-layouts.admin title="Lịch hẹn" heading="Lịch hẹn khách hàng">
    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>Lịch ngày {{ $selectedDate->format('d/m/Y') }}</h2>
                <p>Theo dõi, xác nhận và điều phối nhân viên cho từng khung giờ.</p>
            </div>
            @can('create', App\Models\Appointment::class)
                <a href="{{ route('admin.appointments.create') }}" class="admin-button">+ Tạo lịch hẹn</a>
            @endcan
        </header>

        <x-admin.filter-bar :action="route('admin.appointments.index')" submit-label="Xem lịch">
            <label>Xem theo ngày<input type="date" name="date" value="{{ $selectedDate->toDateString() }}"></label>
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

        <div class="admin-table-wrap">
            <table class="admin-table admin-appointments-table">
                <thead>
                    <tr>
                        <th>Thời gian</th>
                        <th>Khách hàng</th>
                        <th>Nhân viên</th>
                        <th>Dịch vụ</th>
                        <th>Trạng thái</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($appointments as $appointment)
                        <tr>
                            <td>
                                <strong>{{ $appointment->starts_at->format('H:i') }} — {{ ($appointment->ends_at ?? $appointment->starts_at->copy()->addMinutes($appointment->duration_minutes))->format('H:i') }}</strong>
                                <p>{{ $appointment->duration_minutes }} phút</p>
                            </td>
                            <td>
                                <strong>{{ $appointment->customer_name }}</strong>
                                <p><a href="tel:{{ $appointment->customer_phone }}">{{ $appointment->customer_phone }}</a></p>
                            </td>
                            <td>{{ $appointment->employee?->name ?? 'Tiệm sắp xếp' }}</td>
                            <td>
                                @forelse ($appointment->services as $appointmentService)
                                    <span class="admin-service-chip">{{ $appointmentService->service->name }}</span>
                                @empty
                                    <span class="admin-muted-text">Chưa chọn</span>
                                @endforelse
                            </td>
                            <td>
                                @can('update', $appointment)
                                    <form method="POST" action="{{ route('admin.appointments.status', $appointment) }}" class="admin-status-form">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" onchange="this.form.submit()" aria-label="Trạng thái lịch hẹn của {{ $appointment->customer_name }}">
                                            @foreach ($statuses as $value => $label)
                                                <option value="{{ $value }}" @selected($appointment->status->value === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @else
                                    <x-admin.status-badge :status="$appointment->status" />
                                @endcan
                            </td>
                            <td>
                                <div class="admin-row-actions">
                                    @can('update', $appointment)
                                        <a href="{{ route('admin.appointments.edit', $appointment) }}">Chỉnh sửa</a>
                                    @endcan
                                    @if ($appointment->invoice)
                                        @can('view', $appointment->invoice)
                                            <a href="{{ route('admin.invoices.edit', $appointment->invoice) }}">Mở hóa đơn</a>
                                        @endcan
                                    @elseif (in_array($appointment->status, [App\Enums\AppointmentStatus::Completed, App\Enums\AppointmentStatus::CheckedIn], true))
                                        @can('convertToInvoice', $appointment)
                                            <form method="POST" action="{{ route('admin.appointments.convert-to-invoice', $appointment) }}" class="admin-inline-form">
                                                @csrf
                                                <x-admin.submit-button label="Tạo hóa đơn" variant="ghost" />
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty-state :colspan="6" title="Chưa có lịch hẹn trong ngày này" hint="Đổi bộ lọc ngày hoặc tạo lịch hẹn mới." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
