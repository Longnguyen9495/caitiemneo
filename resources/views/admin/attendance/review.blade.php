<x-layouts.admin title="Chấm công cần xem lại" heading="Chấm công cần xem lại">
    @include('admin.partials.staff-nav')

    <x-admin.page-header
        title="Hàng chờ xử lý"
        description="Tăng ca chỉ ảnh hưởng tới lương sau khi được duyệt ở đây."
    />

    <section class="card overflow-hidden mb-3">
        <div class="p-3 border-bottom d-flex align-items-center gap-2">
            <x-admin.icon name="clock" size="18" />
            <h2 class="fs-6 fw-semibold mb-0">Tăng ca chờ duyệt</h2>
            @if ($pendingOvertime->isNotEmpty())
                <span class="badge rounded-pill text-bg-warning">{{ $pendingOvertime->count() }}</span>
            @endif
        </div>

        <table class="table neo-table align-middle mb-0">
                <caption class="visually-hidden">Ca tăng ca chờ duyệt</caption>
            <thead>
                <tr>
                    <th scope="col">Ngày</th>
                    <th scope="col">Nhân viên</th>
                    <th scope="col">Ca</th>
                    <th scope="col">Giờ ra</th>
                    <th scope="col" class="text-end">Vượt ca</th>
                    <th scope="col">Quyết định</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pendingOvertime as $record)
                    <tr>
                        <td class="neo-num fw-semibold">{{ $record->work_date->format('d/m/Y') }}</td>
                        <td data-label="Nhân viên">
                            {{ $record->employee?->name }}
                            <small class="d-block text-body-secondary">{{ $record->branch?->name }}</small>
                        </td>
                        <td data-label="Ca">
                            {{ $record->shift_name }}
                            @if ($record->shiftAssignment)
                                <small class="d-block text-body-secondary neo-num">
                                    dự kiến hết {{ $record->shiftAssignment->planned_end_at->format('H:i') }}
                                </small>
                            @endif
                        </td>
                        <td data-label="Giờ ra" class="neo-num">{{ $record->checked_out_at?->format('H:i') ?? '—' }}</td>
                        <td data-label="Vượt ca" class="text-end neo-num fw-semibold">{{ $record->overtime_minutes }} phút</td>
                        <td data-label="Quyết định">
                            @can('reviewOvertime', $record)
                                @include('admin.attendance.partials.overtime-decision', ['record' => $record])
                            @else
                                <span class="text-body-secondary small">Bạn không có quyền duyệt ca này.</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state
                        :colspan="6"
                        icon="check"
                        title="Không có tăng ca nào đang chờ"
                        hint="Khi nhân viên ra ca muộn hơn giờ dự kiến, ca đó sẽ xuất hiện ở đây."
                    />
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="card overflow-hidden mb-3">
        <div class="p-3 border-bottom d-flex align-items-center gap-2">
            <x-admin.icon name="alert" size="18" />
            <h2 class="fs-6 fw-semibold mb-0">Ca chưa có giờ ra</h2>
        </div>

        <table class="table neo-table align-middle mb-0">
                <caption class="visually-hidden">Ca chưa bấm ra</caption>
            <thead>
                <tr>
                    <th scope="col">Ngày</th>
                    <th scope="col">Nhân viên</th>
                    <th scope="col">Ca</th>
                    <th scope="col">Giờ vào</th>
                    <th scope="col"><span class="visually-hidden">Thao tác</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($openShifts as $record)
                    <tr>
                        <td class="neo-num fw-semibold">{{ $record->work_date->format('d/m/Y') }}</td>
                        <td data-label="Nhân viên">{{ $record->employee?->name }}</td>
                        <td data-label="Ca">{{ $record->shift_name }}</td>
                        <td data-label="Giờ vào" class="neo-num">{{ $record->checked_in_at?->format('H:i') ?? '—' }}</td>
                        <td>
                            @can('update', $record)
                                <a href="{{ route('admin.attendance.edit', $record) }}" class="btn btn-sm btn-outline-secondary">Bổ sung</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state
                        :colspan="5"
                        icon="check"
                        title="Không có ca nào bị bỏ quên giờ ra"
                        hint="Ca của hôm nay chưa tính vào đây vì có thể nhân viên vẫn đang làm."
                    />
                @endforelse
            </tbody>
        </table>
    </section>

    @if ($selfRecorded->isNotEmpty())
        <section class="card overflow-hidden mb-3">
            <div class="p-3 border-bottom d-flex align-items-center gap-2">
                <x-admin.icon name="pin" size="18" />
                <h2 class="fs-6 fw-semibold mb-0">Ca tự chấm cần soát lại</h2>
                <span class="badge rounded-pill text-bg-warning">{{ $selfRecorded->count() }}</span>
            </div>

            <p class="px-3 pt-3 mb-0 small text-body-secondary">
                Người ghi cũng chính là người được tính công. Quản lý đã bị chặn tự ghi,
                nên các dòng dưới đây cần một người khác xác nhận lại.
            </p>

            <table class="table neo-table align-middle mb-0">
                <caption class="visually-hidden">Ca tự chấm cần soát lại</caption>
                <thead>
                    <tr>
                        <th scope="col">Ngày</th>
                        <th scope="col">Nhân viên</th>
                        <th scope="col">Ca</th>
                        <th scope="col">Hệ số</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($selfRecorded as $record)
                        <tr>
                            <td class="neo-num fw-semibold">{{ $record->work_date->format('d/m/Y') }}</td>
                            <td data-label="Nhân viên">
                                {{ $record->employee?->name }}
                                <span class="badge rounded-pill text-bg-warning ms-1">Tự chấm</span>
                            </td>
                            <td data-label="Ca">{{ $record->shift_name }}</td>
                            <td data-label="Hệ số" class="neo-num">{{ $record->shift_value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
    @if ($flaggedGps->isNotEmpty())
        <section class="card overflow-hidden mb-3">
            <div class="p-3 border-bottom d-flex align-items-center gap-2">
                <x-admin.icon name="pin" size="18" />
                <h2 class="fs-6 fw-semibold mb-0">Chấm công GPS cần kiểm tra</h2>
            </div>

            <table class="table neo-table align-middle mb-0">
                <caption class="visually-hidden">Chấm công GPS cần kiểm tra</caption>
                <thead>
                    <tr>
                        <th scope="col">Ngày</th>
                        <th scope="col">Nhân viên</th>
                        <th scope="col">Vào ca</th>
                        <th scope="col">Ra ca</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($flaggedGps as $record)
                        <tr>
                            <td class="neo-num fw-semibold">{{ $record->work_date->format('d/m/Y') }}</td>
                            <td data-label="Nhân viên">{{ $record->employee?->name }}</td>
                            <td data-label="Vào ca">
                                @if ($record->check_in_verification)
                                    <x-admin.status-badge :status="$record->check_in_verification" />
                                    <small class="d-block neo-num text-body-secondary">{{ $record->check_in_distance_meters }} m</small>
                                @else
                                    —
                                @endif
                            </td>
                            <td data-label="Ra ca">
                                @if ($record->check_out_verification)
                                    <x-admin.status-badge :status="$record->check_out_verification" />
                                    <small class="d-block neo-num text-body-secondary">{{ $record->check_out_distance_meters }} m</small>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <section class="card overflow-hidden">
        <div class="p-3 border-bottom">
            <h2 class="fs-6 fw-semibold mb-0">Nhật ký chỉnh sửa</h2>
        </div>

        <table class="table neo-table align-middle mb-0">
                <caption class="visually-hidden">Nhật ký chỉnh sửa chấm công</caption>
            <thead>
                <tr>
                    <th scope="col">Thời điểm</th>
                    <th scope="col">Thao tác</th>
                    <th scope="col">Ca</th>
                    <th scope="col">Người thực hiện</th>
                    <th scope="col">Lý do / thay đổi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($auditLogs as $log)
                    <tr>
                        <td class="neo-num neo-doc-no">{{ $log->created_at->format('d/m H:i') }}</td>
                        <td data-label="Thao tác"><x-admin.status-badge :label="$log->action->label()" :tone="$log->action->tone()" /></td>
                        <td data-label="Ca">
                            {{ $log->attendanceRecord?->employee?->name }}
                            <small class="d-block text-body-secondary neo-num">
                                {{ $log->attendanceRecord?->work_date?->format('d/m/Y') }} · {{ $log->attendanceRecord?->shift_name }}
                            </small>
                        </td>
                        <td data-label="Người thực hiện">{{ $log->actor?->name ?? 'Hệ thống' }}</td>
                        <td data-label="Lý do / thay đổi">
                            @if ($log->reason)<div class="small">{{ $log->reason }}</div>@endif
                            @foreach ($log->changes() as $field => $change)
                                <small class="d-block text-body-secondary">
                                    {{ $field }}: {{ $change['before'] ?? '—' }} → {{ $change['after'] ?? '—' }}
                                </small>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state
                        :colspan="5"
                        icon="inbox"
                        title="Chưa có thay đổi nào được ghi nhận"
                        hint="Mọi lần vào ca, ra ca, sửa tay hay duyệt tăng ca đều để lại một dòng ở đây."
                    />
                @endforelse
            </tbody>
        </table>
    </section>
</x-layouts.admin>
