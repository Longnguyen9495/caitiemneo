<x-layouts.admin title="Lịch hẹn" heading="Lịch hẹn">
    <x-admin.page-header
        :title="'Ngày '.$selectedDate->format('d/m/Y')"
        description="Theo dõi, xác nhận và điều phối nhân viên cho từng khung giờ."
    >
        <x-slot:actions>
            @can('create', App\Models\Appointment::class)
                <a href="{{ route('admin.appointments.create') }}" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                    <x-admin.icon name="plus" size="18" /> Tạo lịch hẹn
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Chuyển ngày bằng một chạm: thao tác thường xuyên nhất của màn hình này. --}}
    <nav class="d-flex align-items-center gap-2 mb-3" aria-label="Chọn ngày">
        <a href="{{ route('admin.appointments.index', ['date' => $selectedDate->copy()->subDay()->toDateString()] + request()->only(['employee_id', 'status'])) }}"
           class="btn btn-light" aria-label="Ngày trước">&lsaquo;</a>

        <form method="GET" action="{{ route('admin.appointments.index') }}" class="flex-grow-1">
            @foreach (request()->only(['employee_id', 'status']) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <label for="date" class="visually-hidden">Xem theo ngày</label>
            <input type="date" id="date" name="date" value="{{ $selectedDate->toDateString() }}"
                   class="form-control text-center fw-semibold" onchange="this.form.submit()">
        </form>

        <a href="{{ route('admin.appointments.index', ['date' => $selectedDate->copy()->addDay()->toDateString()] + request()->only(['employee_id', 'status'])) }}"
           class="btn btn-light" aria-label="Ngày sau">&rsaquo;</a>
    </nav>

    <section class="card overflow-hidden">
        <x-admin.filter-bar :action="route('admin.appointments.index')" submit-label="Áp dụng">
            <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">

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
        </x-admin.filter-bar>

        <ul class="list-unstyled mb-0">
            @forelse ($appointments as $appointment)
                <li class="border-bottom p-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="text-center flex-shrink-0" style="width:3.75rem">
                            <span class="d-block fw-bold neo-num fs-6">{{ $appointment->starts_at->format('H:i') }}</span>
                            <small class="text-body-secondary neo-num">{{ ($appointment->ends_at ?? $appointment->starts_at->copy()->addMinutes($appointment->duration_minutes))->format('H:i') }}</small>
                        </div>

                        <div class="flex-grow-1 min-w-0" style="min-width:0">
                            <p class="mb-1 fw-semibold text-truncate">{{ $appointment->customer_name }}</p>
                            <p class="mb-1 small">
                                <a href="tel:{{ $appointment->customer_phone }}" class="text-decoration-none neo-num">{{ $appointment->customer_phone }}</a>
                                <span class="text-body-secondary">· {{ $appointment->employee?->name ?? 'Tiệm sắp xếp' }}</span>
                            </p>
                            <p class="mb-0 d-flex flex-wrap gap-1">
                                @forelse ($appointment->services as $appointmentService)
                                    <span class="badge rounded-pill text-bg-light border fw-normal">{{ $appointmentService->service->name }}</span>
                                @empty
                                    <span class="small text-body-secondary">Chưa chọn dịch vụ</span>
                                @endforelse
                            </p>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                        @can('update', $appointment)
                            <form method="POST" action="{{ route('admin.appointments.status', $appointment) }}" class="flex-grow-1" style="max-width:13rem">
                                @csrf
                                @method('PATCH')
                                <label class="visually-hidden" for="status-{{ $appointment->id }}">Trạng thái của {{ $appointment->customer_name }}</label>
                                <select class="form-select form-select-sm" id="status-{{ $appointment->id }}" name="status" onchange="this.form.submit()">
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}" @selected($appointment->status->value === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @else
                            <x-admin.status-badge :status="$appointment->status" />
                        @endcan

                        @if ($appointment->invoice)
                            @can('view', $appointment->invoice)
                                <a href="{{ route('admin.invoices.edit', $appointment->invoice) }}" class="btn btn-sm btn-outline-primary">Mở hóa đơn</a>
                            @endcan
                        @elseif (in_array($appointment->status, [App\Enums\AppointmentStatus::Completed, App\Enums\AppointmentStatus::CheckedIn], true))
                            @can('convertToInvoice', $appointment)
                                <form method="POST" action="{{ route('admin.appointments.convert-to-invoice', $appointment) }}">
                                    @csrf
                                    <x-admin.submit-button label="Tạo hóa đơn" variant="outline-primary btn-sm" />
                                </form>
                            @endcan
                        @endif

                        @can('update', $appointment)
                            <a href="{{ route('admin.appointments.edit', $appointment) }}" class="btn btn-sm btn-link text-decoration-none ms-auto">Sửa</a>
                        @endcan
                    </div>
                </li>
            @empty
                <li>
                    <x-admin.empty-state
                        icon="calendar"
                        title="Chưa có lịch hẹn trong ngày này"
                        hint="Đổi ngày ở thanh trên, hoặc tạo lịch hẹn mới."
                    />
                </li>
            @endforelse
        </ul>
    </section>
</x-layouts.admin>
