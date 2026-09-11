@php
    $hasGps = $record->check_in_verification !== null || $record->check_out_verification !== null;
@endphp

@if ($hasGps || $record->overtime_minutes > 0 || $record->late_minutes > 0)
    {{--
        Bằng chứng máy thu được. Cố ý không hiển thị toạ độ: người duyệt cần
        biết "trong hay ngoài vùng, cách bao xa", không cần biết nhân viên
        đứng chính xác ở đâu.
    --}}
    <section class="card p-3 mb-3">
        <h2 class="fs-6 fw-semibold mb-2">Dữ liệu hệ thống ghi nhận</h2>

        <div class="row g-2">
            <div class="col-6 col-lg-3">
                <dl class="neo-stat mb-0">
                    <dt>Nguồn</dt>
                    <dd class="fs-6">{{ $record->source?->label() ?? '—' }}</dd>
                </dl>
            </div>

            <div class="col-6 col-lg-3">
                <dl class="neo-stat mb-0">
                    <dt>Đi trễ</dt>
                    <dd class="fs-6 neo-num">{{ $record->late_minutes }} phút</dd>
                </dl>
            </div>

            <div class="col-6 col-lg-3">
                <dl class="neo-stat mb-0">
                    <dt>Vượt ca</dt>
                    <dd class="fs-6 neo-num">{{ $record->overtime_minutes }} phút</dd>
                    <small>{{ $record->overtime_status?->label() }}</small>
                </dl>
            </div>

            <div class="col-6 col-lg-3">
                <dl class="neo-stat mb-0">
                    <dt>Tăng ca đã duyệt</dt>
                    <dd class="fs-6 neo-num">{{ $record->approved_overtime_minutes }} phút</dd>
                    @if ($record->overtimeApprover)
                        <small>{{ $record->overtimeApprover->name }} · {{ $record->overtime_approved_at?->format('d/m H:i') }}</small>
                    @endif
                </dl>
            </div>
        </div>

        @if ($hasGps)
            <div class="row g-2 mt-1">
                <div class="col-12 col-lg-6">
                    <p class="small mb-1 fw-semibold">Vào ca</p>
                    <p class="small text-body-secondary mb-0">
                        @if ($record->check_in_verification)
                            {{ $record->check_in_verification->label() }} ·
                            cách <span class="neo-num">{{ $record->check_in_distance_meters }}</span> m ·
                            sai số <span class="neo-num">{{ $record->check_in_accuracy_meters }}</span> m
                            @if ($record->check_in_ip)<br>IP {{ $record->check_in_ip }}@endif
                        @else
                            Không có dữ liệu GPS.
                        @endif
                    </p>
                </div>

                <div class="col-12 col-lg-6">
                    <p class="small mb-1 fw-semibold">Ra ca</p>
                    <p class="small text-body-secondary mb-0">
                        @if ($record->check_out_verification)
                            {{ $record->check_out_verification->label() }} ·
                            cách <span class="neo-num">{{ $record->check_out_distance_meters }}</span> m ·
                            sai số <span class="neo-num">{{ $record->check_out_accuracy_meters }}</span> m
                            @if ($record->check_out_ip)<br>IP {{ $record->check_out_ip }}@endif
                        @else
                            Không có dữ liệu GPS.
                        @endif
                    </p>
                </div>
            </div>
        @endif

        @if ($record->overtime_status === App\Enums\OvertimeStatus::Pending)
            @can('reviewOvertime', $record)
                <hr>
                <p class="small fw-semibold mb-2">Duyệt tăng ca</p>
                @include('admin.attendance.partials.overtime-decision', ['record' => $record])
            @endcan
        @endif
    </section>
@endif
