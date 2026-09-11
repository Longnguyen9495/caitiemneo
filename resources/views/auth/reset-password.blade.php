<x-guest-layout>
    <header class="auth-card-header">
        <p class="eyebrow">Khôi phục truy cập</p>
        <h2>Đặt lại mật khẩu</h2>
        <p>Chọn một mật khẩu mới cho tài khoản của bạn.</p>
    </header>

    <form method="POST" action="{{ route('password.store') }}" class="auth-form">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <label for="email">
            Email
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
            @error('email')<small>{{ $message }}</small>@enderror
        </label>

        <label for="password">
            Mật khẩu mới
            <input id="password" type="password" name="password" required autocomplete="new-password">
            @error('password')<small>{{ $message }}</small>@enderror
        </label>

        <label for="password_confirmation">
            Xác nhận mật khẩu
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            @error('password_confirmation')<small>{{ $message }}</small>@enderror
        </label>

        <div class="auth-actions">
            <a href="{{ route('login') }}">← Quay lại đăng nhập</a>
            <button type="submit">Đặt lại mật khẩu <span aria-hidden="true">↗</span></button>
        </div>
    </form>
</x-guest-layout>
