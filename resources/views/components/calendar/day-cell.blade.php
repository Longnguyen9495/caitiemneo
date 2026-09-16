@props(['day', 'viewModel'])

@php
    /** @var \App\Support\CalendarDay $day */
    /** @var \App\Support\CalendarViewModel $viewModel */

    $iso = $day->isoDate;
    $items = $viewModel->itemsForDay($iso);
    $tone = $viewModel->dayTone($iso);
    $recordCount = $viewModel->dayRecordCount($iso);
    $warningCount = $viewModel->dayWarningCount($iso);

    $cellClasses = ['neo-cal__cell'];
    if (! $day->isInMonth) {
        $cellClasses[] = 'is-outside';
    }
    if ($day->isToday) {
        $cellClasses[] = 'is-today';
    }
    if ($items->isNotEmpty()) {
        $cellClasses[] = 'has-data';
    }
    $cellClass = implode(' ', $cellClasses);

    $toneClass = match (preg_replace('/^is-/', '', $tone)) {
        'success' => 'neo-cal__tone--success',
        'danger' => 'neo-cal__tone--danger',
        'warning' => 'neo-cal__tone--warning',
        'active' => 'neo-cal__tone--active',
        'planned' => 'neo-cal__tone--planned',
        default => 'neo-cal__tone--empty',
    };

    $accessibleLabel = $day->accessibleLabel;
    if ($items->isNotEmpty()) {
        $summaries = $items->map(fn ($item) => $item->accessibleSummary())->filter()->implode('; ');
        $accessibleLabel .= ': ' . $summaries;
    }
@endphp

{{--
    Nút ngày có accessible name đầy đủ.
    Trạng thái không chỉ thể hiện bằng màu; có text/icon và aria-label.
    Touch target tối thiểu 44px.
    Khi không mở được panel (không có dữ liệu hoặc chỉ đọc), button bị disabled
    để không gây hiểu lầm là có thể tương tác.
--}}
<button
    type="button"
    class="{{ $cellClass }} {{ $toneClass }}"
    aria-label="{{ $accessibleLabel }}"
    @if ($viewModel->canOpenDetails && $items->isNotEmpty())
        @click="$dispatch('calendar:show-detail', { date: '{{ $iso }}' })"
        data-cal-date="{{ $iso }}"
    @else
        disabled
    @endif
>
    <span class="neo-cal__daynum" aria-hidden="true">{{ $day->dayOfMonth }}</span>

    <span class="neo-cal__indicators" aria-hidden="true">
        @if ($recordCount > 1)
            <span class="neo-cal__badge neo-cal__badge--count">{{ $recordCount }}</span>
        @endif
        @if ($warningCount > 0)
            <span class="neo-cal__badge neo-cal__badge--warn" aria-label="{{ $warningCount }} cảnh báo">!</span>
        @endif
    </span>

    {{-- Tone strip: thanh màu ở dưới cùng, không phụ thuộc màu duy nhất --}}
    <span class="neo-cal__strip" aria-hidden="true"></span>
</button>
