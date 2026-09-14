<x-layouts.admin title="Chấm công" heading="Chấm công">
    @php
        $employee = auth()->user();
        $branch = $openRecord?->branch ?? $actionableAssignment?->branch;
    @endphp

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <section class="card p-3 p-lg-4" x-data="clockPanel">
                <p class="small text-body-secondary mb-1">{{ $employee->name }}</p>
                <h2 class="neo-display fs-4 mb-1">{{ $now->translatedFormat('l, d/m/Y') }}</h2>
                <p class="neo-num fs-2 mb-3" aria-live="off">{{ $now->format('H:i') }}</p>

                @if ($openRecord)
                    {{-- Đang trong ca: chỉ còn một việc để làm là ra ca. --}}
                    <div class="alert alert-success border-0 d-flex align-items-start gap-2" role="status">
                        <x-admin.icon name="check" size="18" class="flex-shrink-0 mt-1" />
                        <div>
                            <strong>Đang làm ca {{ $openRecord->shift_name }}</strong>
                            <div class="small">
                                Vào ca lúc <span class="neo-num">{{ $openRecord->checked_in_at->format('H:i') }}</span>
                                @if ($openRecord->branch) · {{ $openRecord->branch->name }} @endif
                                @if ($openRecord->late_minutes > 0)
                                    · trễ <span class="neo-num">{{ $openRecord->late_minutes }}</span> phút
                                @endif
                            </div>
                            @if ($openRecord->shiftAssignment)
                                <div class="small text-body-secondary">
                                    Dự kiến kết thúc <span class="neo-num">{{ $openRecord->shiftAssignment->planned_end_at->format('H:i') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <x-attendance.clock-button
                        :action="route('attendance.check-out')"
                        label="Ra ca"
                        variant="danger"
                    />
                @elseif ($actionableAssignment)
                    <dl class="neo-stat mb-3">
                        <dt>Ca hôm nay</dt>
                        <dd class="fs-5">{{ $actionableAssignment->shift_name }}</dd>
                        <small class="neo-num">{{ $actionableAssignment->timeRangeLabel() }}</small>
                    </dl>

                    <p class="small text-body-secondary mb-3">
                        {{ $actionableAssignment->branch?->name }} · hệ số ca
                        <span class="neo-num">{{ rtrim(rtrim((string) $actionableAssignment->shift_value, '0'), '.') }}</span>
                        @if ($actionableAssignment->grace_minutes > 0)
                            · ân hạn <span class="neo-num">{{ $actionableAssignment->grace_minutes }}</span> phút
                        @endif
                    </p>

                    <x-attendance.clock-button
                        :action="route('attendance.check-in')"
                        label="Vào ca"
                        variant="primary"
                    />
                @else
                    <x-admin.empty-body
                        icon="clock"
                        title="Hiện chưa có ca nào để chấm công"
                        hint="Bạn chỉ bấm được Vào ca trong khoảng từ 30 phút trước giờ bắt đầu tới hết ca. Nếu lịch sai, hãy báo quản lý."
                    />
                @endif
            </section>
        </div>

        <div class="col-12 col-lg-5">
            <section class="card p-3 mb-3">
                <h3 class="fs-6 fw-semibold mb-2">Ca trong hôm nay</h3>

                @forelse ($todayAssignments as $assignment)
                    <div class="d-flex justify-content-between align-items-start gap-2 py-2 border-bottom">
                        <div class="min-w-0" style="min-width:0">
                            <p class="mb-0 fw-semibold text-truncate">{{ $assignment->shift_name }}</p>
                            <small class="neo-num text-body-secondary">{{ $assignment->timeRangeLabel() }}</small>
                        </div>
                        @if ($assignment->attendanceRecord)
                            <x-admin.status-badge :status="$assignment->attendanceRecord->status" />
                        @else
                            <span class="badge rounded-pill text-bg-light border">Chưa chấm</span>
                        @endif
                    </div>
                @empty
                    <p class="small text-body-secondary mb-0">Hôm nay bạn không có ca nào được phân.</p>
                @endforelse
            </section>

            <section class="card p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h3 class="fs-6 fw-semibold mb-0">Lịch sắp tới</h3>
                    <a class="small" href="{{ route('admin.shift-schedule.index') }}">Xem lịch tuần</a>
                </div>

                @forelse ($upcomingAssignments as $assignment)
                    <div class="d-flex justify-content-between align-items-center gap-2 py-1">
                        <span class="neo-num small">{{ $assignment->work_date->format('d/m') }}</span>
                        <span class="small text-truncate flex-grow-1">{{ $assignment->shift_name }}</span>
                        <span class="neo-num small text-body-secondary">{{ $assignment->planned_start_at->format('H:i') }}</span>
                    </div>
                @empty
                    <p class="small text-body-secondary mb-0">Chưa có ca nào được phân trong 7 ngày tới.</p>
                @endforelse
            </section>
        </div>

        <div class="col-12">
            <section class="card overflow-hidden">
                <div class="p-3 border-bottom">
                    <h3 class="fs-6 fw-semibold mb-0">Chấm công 14 ngày gần đây</h3>
                </div>

                <table class="table neo-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Ngày</th>
                            <th scope="col">Ca</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Giờ vào / ra</th>
                            <th scope="col" class="text-end">Trễ</th>
                            <th scope="col">Tăng ca</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentRecords as $record)
                            <tr>
                                <td class="neo-num fw-semibold">{{ $record->work_date->format('d/m/Y') }}</td>
                                <td data-label="Ca">{{ $record->shift_name }}</td>
                                <td data-label="Trạng thái" class="neo-table__status"><x-admin.status-badge :status="$record->status" /></td>
                                <td data-label="Giờ vào / ra" class="neo-num">
                                    {{ $record->checked_in_at?->format('H:i') ?? '—' }} / {{ $record->checked_out_at?->format('H:i') ?? '—' }}
                                </td>
                                <td data-label="Trễ" class="text-end neo-num">{{ $record->late_minutes > 0 ? $record->late_minutes.' phút' : '—' }}</td>
                                <td data-label="Tăng ca">
                                    @if ($record->overtime_minutes > 0)
                                        <span class="neo-num">{{ $record->overtime_minutes }} phút</span>
                                        <x-admin.status-badge :status="$record->overtime_status" class="ms-1" />
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty-state
                                :colspan="6"
                                icon="clock"
                                title="Chưa có lịch sử chấm công"
                                hint="Bản ghi sẽ xuất hiện ở đây ngay sau lần vào ca đầu tiên."
                            />
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</x-layouts.admin>
