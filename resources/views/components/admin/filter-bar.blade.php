@props(['action', 'method' => 'GET', 'reset' => null, 'submitLabel' => 'Lọc'])

<form class="admin-filter-bar" method="{{ $method }}" action="{{ $action }}">
    {{ $slot }}

    <div class="admin-filter-actions">
        <button type="submit">{{ $submitLabel }}</button>
        @if (collect(request()->query())->except('page')->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty())
            <a href="{{ $reset ?? $action }}">Xóa lọc</a>
        @endif
    </div>
</form>
