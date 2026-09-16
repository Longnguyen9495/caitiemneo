@props(['items'])

{{--
    Standalone legend component, used when the grid legend is not sufficient.
    Expects array<string, string> where key = label, value = tone class.
--}}

<div class="neo-cal__legend" aria-label="Chú thích trạng thái">
    @foreach ($items as $label => $tone)
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
