@php
    /** @var \App\Support\CalendarViewModel $calendar */
    /** @var \App\Models\Payroll $payroll */
@endphp

@if (isset($calendar))
    <section class="card mb-3 overflow-hidden" x-data="calendarPanel()" @calendar:show-detail.window="open($event.detail.date)">
        <div class="card-header">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <div>
                    <h2 class="neo-display fs-5 mb-0">Lịch đối soát chấm công</h2>
                    <p class="mb-0 small text-body-secondary">
                        @if ($payroll->status->value === 'draft')
                            Dữ liệu chấm công hiện tại — sẽ được chụp khi chốt bảng lương.
                        @elseif (in_array($payroll->status->value, ['finalized', 'paid'], true))
                            Kỳ lương đã chốt — lương và KPI là snapshot đã khóa. Lịch hiển thị dữ liệu đối chiếu hiện tại.
                        @else
                            Chỉ đọc.
                        @endif
                    </p>
                </div>
                <span class="badge rounded-pill {{ $payroll->status->tone() }}">
                    {{ $payroll->status->label() }}
                </span>
            </div>
        </div>

        <x-calendar.grid :viewModel="$calendar" />

        {{-- Panel chi tiết ngày — server-render, Alpine chỉ điều khiển hiển thị --}}
        <div class="neo-cal__panel" :class="{ 'is-open': activeDate !== null }" x-show="activeDate !== null" x-cloak>
            <template x-if="activeDate !== null">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h4 class="fs-6 fw-semibold mb-0" x-text="activeDate"></h4>
                        <button type="button" class="btn btn-sm btn-link" @click="close()">Đóng</button>
                    </div>

                    @foreach ($calendar->days as $day)
                        <div x-show="activeDate === '{{ $day->date->toDateString() }}'">
                            @php
                                $items = $calendar->itemsForDay($day->date->toDateString());
                            @endphp

                            @forelse ($items as $item)
                                <div class="mb-2 pb-2 border-bottom">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge {{ $item->status?->tone() ?? 'text-bg-light border' }}">
                                            {{ $item->status?->label() ?? '—' }}
                                        </span>
                                        <span class="fw-medium">{{ $item->shiftName }}</span>
                                    </div>

                                    @if ($item->checkedInAt || $item->checkedOutAt)
                                        <p class="mb-0 small text-body-secondary">
                                            @if ($item->checkedInAt)
                                                <span class="me-2">Vào {{ $item->checkedInAt }}</span>
                                            @endif
                                            @if ($item->checkedOutAt)
                                                <span>Ra {{ $item->checkedOutAt }}</span>
                                            @endif
                                        </p>
                                    @endif

                                    @if ($item->lateMinutes > 0)
                                        <p class="mb-0 small text-warning">Đi muộn {{ $item->lateMinutes }} phút</p>
                                    @endif

                                    @if ($item->overtimeMinutes > 0)
                                        <p class="mb-0 small text-body-secondary">
                                            Tăng ca {{ $item->overtimeMinutes }} phút
                                            @if ($item->overtimeNeedsReview())
                                                <span class="badge text-bg-warning">chờ duyệt</span>
                                            @endif
                                        </p>
                                    @endif

                                    @if ($item->isLockedByPayroll)
                                        <p class="mb-0 small text-success">
                                            <x-admin.icon name="lock" size="12" /> Đã khóa bởi kỳ lương
                                        </p>
                                    @endif

                                    @if ($item->note)
                                        <p class="mb-0 small text-success">{{ $item->note }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="text-body-secondary small mb-0">Không có dữ liệu chấm công trong ngày này.</p>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </template>
        </div>
    </section>
@endif
