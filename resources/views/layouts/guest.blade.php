<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Cái Tiệm Neo') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-intro">
            <a href="{{ route('home') }}" class="auth-brand"><span>N</span> Cái Tiệm Neo</a>
            <p class="eyebrow">Khu vực điều hành</p>
            <h1>Chăm chút từng<br><em>khoảnh khắc đẹp.</em></h1>
            <p>Đăng nhập để quản lý lịch hẹn, dịch vụ và vận hành tiệm một cách nhẹ nhàng, rõ ràng.</p>
            <a href="{{ route('home') }}" class="auth-back">← Về trang website</a>
        </section>
        <section class="auth-card-wrap">
            <div class="auth-card">
                {{ $slot }}
            </div>
        </section>
    </main>
</body>
</html>
