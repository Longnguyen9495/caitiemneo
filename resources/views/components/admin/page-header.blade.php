@props(['title', 'description' => null, 'breadcrumbs' => []])

<div class="admin-page-header">
    @if (! empty($breadcrumbs))
        <nav class="admin-breadcrumbs" aria-label="Đường dẫn">
            @foreach ($breadcrumbs as $label => $url)
                @if ($url)
                    <a href="{{ $url }}">{{ $label }}</a><span aria-hidden="true">/</span>
                @else
                    <strong>{{ $label }}</strong>
                @endif
            @endforeach
        </nav>
    @endif

    <div class="admin-page-header-main">
        <div>
            <h2>{{ $title }}</h2>
            @if ($description)<p>{{ $description }}</p>@endif
        </div>
        @isset($actions)
            <div class="admin-page-actions">{{ $actions }}</div>
        @endisset
    </div>
</div>
