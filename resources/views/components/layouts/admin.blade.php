@props([
    'title' => 'Quản trị',
    'heading' => 'Tổng quan',
])

@php
    use App\Models\Appointment;
    use App\Models\Branch;
    use App\Models\CashTransaction;
    use App\Models\Invoice;
    use App\Models\Payroll;
    use App\Models\Product;
    use App\Models\Service;
    use App\Models\User;

    $user = auth()->user();

    $navigation = collect([
        ['label' => 'Tổng quan', 'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard', 'visible' => true],
        ['label' => 'Lịch hẹn', 'route' => 'admin.appointments.index', 'pattern' => 'admin.appointments.*', 'visible' => $user->can('viewAny', Appointment::class)],
        ['label' => 'Dịch vụ', 'route' => 'admin.services.index', 'pattern' => 'admin.services.*', 'visible' => $user->can('viewAny', Service::class)],
        ['label' => 'Hóa đơn & thu chi', 'route' => 'admin.invoices.index', 'pattern' => ['admin.invoices.*', 'admin.cash.*'], 'visible' => $user->can('viewAny', Invoice::class) || $user->can('viewAny', CashTransaction::class)],
        ['label' => 'Kho vật tư', 'route' => 'admin.products.index', 'pattern' => ['admin.products.*', 'admin.suppliers.*', 'admin.inventory.*', 'admin.stock-transfers.*'], 'visible' => $user->can('viewAny', Product::class)],
        ['label' => 'Nhân sự & lương', 'route' => 'admin.employees.index', 'pattern' => ['admin.employees.*', 'admin.attendance.*', 'admin.payrolls.*'], 'visible' => $user->can('viewAny', User::class) || $user->can('viewAny', Payroll::class)],
        ['label' => 'Báo cáo', 'route' => 'admin.reports.index', 'pattern' => 'admin.reports.*', 'visible' => \Illuminate\Support\Facades\Gate::allows('view-reports')],
        ['label' => 'Chi nhánh', 'route' => 'admin.branches.index', 'pattern' => 'admin.branches.*', 'visible' => $user->can('viewAny', Branch::class)],
    ])->where('visible', true);
@endphp

<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Cái Tiệm Neo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body">
    <div class="admin-shell" x-data="{ sidebarOpen: false }">
        <aside class="admin-sidebar" :class="{ 'is-open': sidebarOpen }">
            <a href="{{ route('admin.dashboard') }}" class="admin-brand"><span>N</span><strong>Cái Tiệm Neo</strong></a>
            <p class="admin-nav-label">Điều hành tiệm</p>
            <nav class="admin-nav" aria-label="Menu quản trị">
                @foreach ($navigation as $item)
                    <a
                        href="{{ route($item['route']) }}"
                        @class(['is-active' => request()->routeIs($item['pattern'])])
                        @if (request()->routeIs($item['pattern'])) aria-current="page" @endif
                    >{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="admin-sidebar-footer">
                <p>{{ $user->name }}</p>
                <span>{{ $user->role->label() }}</span>
            </div>
        </aside>
        <div class="admin-content">
            <header class="admin-topbar">
                <button type="button" class="admin-menu-button" @click="sidebarOpen = !sidebarOpen" aria-label="Mở menu quản trị">☰</button>
                <div><p class="admin-kicker">Cái Tiệm Neo · quản trị</p><h1>{{ $heading }}</h1></div>
                <div class="admin-top-actions"><x-admin.branch-switcher /><a href="{{ route('home') }}" target="_blank">Xem website ↗</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Đăng xuất</button></form></div>
            </header>
            <main class="admin-main">
                @if (session('success'))
                    <div class="admin-flash" role="status">{{ session('success') }}</div>
                @endif

                @if (session('error'))
                    <div class="admin-flash is-error" role="alert">{{ session('error') }}</div>
                @endif

                <x-admin.errors />

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
