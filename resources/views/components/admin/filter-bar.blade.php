@props(['action', 'method' => 'GET', 'reset' => null, 'submitLabel' => 'Lọc'])

@php
    // Đếm số bộ lọc đang bật để hiện huy hiệu, tránh việc người dùng quên là
    // danh sách đang bị lọc rồi tưởng mất dữ liệu.
    $active = collect(request()->query())
        ->except('page')
        ->filter(fn ($value) => $value !== null && $value !== '');
@endphp

<div class="border-bottom">
    {{-- Trên điện thoại bộ lọc gập lại để danh sách lên ngay đầu màn hình. --}}
    <button class="btn btn-link text-decoration-none d-flex d-lg-none align-items-center gap-2 w-100 px-3 py-2 text-body"
            type="button" data-bs-toggle="collapse" data-bs-target="#neoFilters"
            aria-expanded="{{ $active->isNotEmpty() ? 'true' : 'false' }}" aria-controls="neoFilters">
        <x-admin.icon name="filter" size="18" />
        <span class="fw-semibold small">Bộ lọc</span>
        @if ($active->isNotEmpty())
            <span class="badge rounded-pill text-bg-primary">{{ $active->count() }}</span>
        @endif
    </button>

    <div @class(['collapse', 'd-lg-block', 'show' => $active->isNotEmpty()]) id="neoFilters">
        <form method="{{ $method }}" action="{{ $action }}" class="row g-2 g-lg-3 align-items-end p-3">
            {{ $slot }}

            <div class="col-12 col-lg-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill flex-lg-grow-0">{{ $submitLabel }}</button>
                @if ($active->isNotEmpty())
                    <a href="{{ $reset ?? $action }}" class="btn btn-light">Xóa lọc</a>
                @endif
            </div>
        </form>
    </div>
</div>
