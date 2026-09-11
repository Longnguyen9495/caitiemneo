@props(['title', 'description' => null, 'breadcrumbs' => []])

<div class="mb-3">
    @if (! empty($breadcrumbs))
        <nav aria-label="Đường dẫn">
            <ol class="breadcrumb small mb-2">
                @foreach ($breadcrumbs as $label => $url)
                    <li @class(['breadcrumb-item', 'active' => ! $url]) @if (! $url) aria-current="page" @endif>
                        @if ($url)<a href="{{ $url }}">{{ $label }}</a>@else{{ $label }}@endif
                    </li>
                @endforeach
            </ol>
        </nav>
    @endif

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div class="min-w-0" style="min-width:0">
            <h2 class="neo-display fs-3 mb-1">{{ $title }}</h2>
            @if ($description)<p class="mb-0 small text-body-secondary">{{ $description }}</p>@endif
        </div>
        @isset($actions)
            <div class="d-flex flex-wrap align-items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
