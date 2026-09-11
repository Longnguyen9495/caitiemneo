<x-guest-layout>
    <header class="auth-card-header">
        <p class="eyebrow">Chào mừng trở lại</p>
        <h2>Đăng nhập quản trị</h2>
        <p>Nhập thông tin tài khoản để tiếp tục.</p>
    </header>

    @if (session('status'))
        <div class="auth-alert auth-success" role="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <label for="login">
            Tên đăng nhập hoặc email
            <input id="login" type="text" name="login" value="{{ old('login') }}" required autofocus autocomplete="username">
            @error('login')<small>{{ $message }}</small>@enderror
        </label>

        <label for="password">
            Mật khẩu
            <input id="password" type="password" name="password" required autocomplete="current-password">
            @error('password')<small>{{ $message }}</small>@enderror
        </label>

        <label class="auth-remember" for="remember">
            <input id="remember" type="checkbox" name="remember">
            <span>Ghi nhớ đăng nhập</span>
        </label>

        <div class="auth-actions">
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">Quên mật khẩu?</a>
            @endif
            <button type="submit">Đăng nhập <span aria-hidden="true">↗</span></button>
        </div>
    </form>
</x-guest-layout>
