@props(['viewModel', 'toolbarSlot' => null, 'detailSlot' => null])

@php
    /** @var \App\Support\CalendarViewModel $viewModel */
@endphp

<div
    class="neo-cal"
    x-data="calendarPanel()"
    @calendar:show-detail.window="open($event.detail.date)"
>
    {{-- Toolbar tháng/kỳ --}}
    <div class="neo-cal__toolbar">
        <div class="neo-cal__nav">
            <a href="{{ $viewModel->prevUrl }}" class="neo-cal__arrow" aria-label="Tháng trước">
                <x-admin.icon name="chevron-down" size="20" class="neo-cal__arrow-icon" />
            </a>
            <a href="{{ $viewModel->currentUrl }}" class="neo-cal__title" aria-label="Về tháng hiện tại">
                {{ $viewModel->title }}
            </a>
            <a href="{{ $viewModel->nextUrl }}" class="neo-cal__arrow" aria-label="Tháng sau">
                <x-admin.icon name="chevron-down" size="20" class="neo-cal__arrow-icon" />
            </a>
        </div>

        @if ($toolbarSlot)
            <div class="neo-cal__toolbar-slot">{{ $toolbarSlot }}</div>
        @endif
    </div>

    {{-- Hàng nhãn thứ --}}
    <div class="neo-cal__weekdays" role="row">
        @foreach ($viewModel->weekDayLabels as $label)
            <div class="neo-cal__weekday" role="columnheader" aria-hidden="true">{{ $label }}</div>
        @endforeach
    </div>

    {{-- Lưới ngày --}}
    <div class="neo-cal__grid" role="grid">
        @foreach ($viewModel->days->chunk(7) as $week)
            <div class="neo-cal__row" role="row">
                @foreach ($week as $day)
                    <x-calendar.day-cell :day="$day" :viewModel="$viewModel" />
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- Legend --}}
    @if (filled($viewModel->legend))
        <div class="neo-cal__legend" aria-label="Chú thích trạng thái">
            @foreach ($viewModel->legend as $label => $tone)
                @php
                    $toneBase = preg_replace('/^is-/', '', $tone);
                    $dotClass = match ($toneBase) {
                        'success' => 'neo-cal__dot--success',
                        'danger' => 'neo-cal__dot--danger',
                        'warning' => 'neo-cal__dot--warning',
                        'active' => 'neo-cal__dot--active',
                        'planned' => 'neo-cal__dot--planned',
                        default => 'neo-cal__dot--muted',
                    };
                @endphp
                <span class="neo-cal__legend-item">
                    <span class="neo-cal__dot {{ $dotClass }}" aria-hidden="true"></span>
                    <span>{{ $label }}</span>
                </span>
            @endforeach
        </div>
    @endif

    {{-- Detail panel (rendered server-side, toggled by Alpine) --}}
    <template x-if="activeDate">
        <div class="neo-cal__panel" role="dialog" aria-modal="true" aria-label="Chi tiết ngày" @click.away="close()">
            <div class="neo-cal__panel-header">
                <span class="neo-cal__panel-title" x-text="activeDateLabel"></span>
                <button type="button" class="neo-cal__panel-close" @click="close()" aria-label="Đóng">
                    <x-admin.icon name="chevron-down" size="20" />
                </button>
            </div>
            <div class="neo-cal__panel-body">
                {{ $detailSlot }}
            </div>
        </div>
    </template>
</div>
