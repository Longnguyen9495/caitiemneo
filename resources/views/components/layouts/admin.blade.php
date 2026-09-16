@props([
    'title' => 'Quản trị',
    'heading' => 'Tổng quan',
])

@php
    $user = auth()->user();
    $navigation = App\Support\AdminNavigation::for($user);
    $primaryNav = App\Support\AdminNavigation::primary($navigation);
    $navigationGroups = App\Support\AdminNavigation::groups($navigation);
@endphp

<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#7b2f50">
    <meta name="description" content="Khu quản trị tiệm nail Cái Tiệm Neo: lịch hẹn, hóa đơn, kho vật tư, chấm công và lương.">
    <title>{{ $title }} · Cái Tiệm Neo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/scss/admin.scss', 'resources/js/admin.js'])
</head>
<body class="neo">
    <a class="neo-skip" href="#neo-main">Bỏ qua điều hướng</a>

    <div class="d-flex">
        <aside class="neo-sidebar p-3">
            <a href="{{ route('admin.dashboard') }}" class="d-flex align-items-center gap-2 text-white text-decoration-none px-2 py-3 mb-2">
                <span class="d-inline-grid place-items-center rounded-circle border border-2 fst-italic"
                      style="width:32px;height:32px;place-items:center;display:grid;border-color:rgba(255,255,255,.35)!important">N</span>
                <strong class="neo-display fs-5">Cái Tiệm Neo</strong>
            </a>

            <nav class="d-grid gap-1" aria-label="Menu quản trị">
                <x-admin.nav-groups :groups="$navigationGroups" />
            </nav>

            <div class="mt-4 pt-3 border-top" style="border-color:rgba(255,255,255,.16)!important">
                <p class="mb-0 text-white fw-semibold small">{{ $user->name }}</p>
                <p class="mb-2 small" style="color:var(--bs-warning-text-emphasis);color:#f4aac5">{{ $user->role->label() }}</p>
                <a href="{{ route('profile.edit') }}" @class(['neo-navlink mb-1', 'is-active' => request()->routeIs('profile.*')])>
                    <x-admin.icon name="people" />
                    <span>Tài khoản của tôi</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-light w-100">Đăng xuất</button>
                </form>
            </div>
        </aside>

        <div class="flex-grow-1 min-w-0" style="min-width:0">
            <header class="neo-topbar sticky-top d-flex align-items-center gap-2 px-3">
                <button class="btn btn-link text-decoration-none p-1 d-lg-none" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#neoMenu" aria-controls="neoMenu"
                        aria-label="Mở menu quản trị">
                    <x-admin.icon name="menu" size="24" />
                </button>

                <h1 class="neo-topbar__title mb-0 flex-grow-1 text-truncate">{{ $heading }}</h1>

                <div class="neo-topbar__actions">
                    @php($unreadNotificationCount = $user->unreadNotifications()->count())
                    <a href="{{ route('admin.notifications.index') }}"
                       @class(['neo-notification-button', 'position-relative', 'is-active' => request()->routeIs('admin.notifications.*')])
                       aria-label="Thông báo{{ $unreadNotificationCount ? ': '.$unreadNotificationCount.' chưa đọc' : '' }}"
                       @if (request()->routeIs('admin.notifications.*')) aria-current="page" @endif>
                        <x-admin.icon name="bell" size="18" />
                        @if ($unreadNotificationCount > 0)
                            <span class="neo-notification-button__badge" aria-hidden="true">{{ min($unreadNotificationCount, 99) }}</span>
                        @endif
                    </a>

                    <x-admin.branch-switcher />
                </div>
            </header>

            <main id="neo-main" class="p-3 p-lg-4">
                <x-admin.flash />

                {{-- Tóm tắt lỗi đặt một lần ở layout: mọi biểu mẫu trong khu quản
                     trị đều có, không phải nhớ thêm vào từng trang. --}}
                <x-admin.error-summary class="mb-3" />

                {{ $slot }}
            </main>
        </div>
    </div>

    @include('admin.partials.mobile-menu', ['navigationGroups' => $navigationGroups, 'user' => $user])

    <nav class="neo-tabbar" aria-label="Điều hướng nhanh">
        @foreach ($primaryNav as $item)
            <a href="{{ route($item['route']) }}"
               @class(['neo-tabbar__item', 'position-relative', 'is-active' => request()->routeIs($item['pattern'])])
               @if (request()->routeIs($item['pattern'])) aria-current="page" @endif>
                <x-admin.icon :name="$item['icon']" />
                <span>{{ $item['short'] }}</span>
            </a>
        @endforeach

        <button type="button" class="neo-tabbar__item border-0 bg-transparent"
                data-bs-toggle="offcanvas" data-bs-target="#neoMenu" aria-controls="neoMenu">
            <x-admin.icon name="more" />
            <span>Thêm</span>
        </button>
    </nav>

    <x-admin.confirm-modal />
    <x-admin.notice-modal />
</body>
</html>
