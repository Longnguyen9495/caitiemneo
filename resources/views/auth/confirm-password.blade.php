<x-guest-layout>
    <header class="auth-card-header">
        <p class="eyebrow">Xác thực bảo mật</p>
        <h2>Xác nhận mật khẩu</h2>
        <p>Đây là khu vực bảo mật. Vui lòng nhập lại mật khẩu để tiếp tục.</p>
    </header>

    <form method="POST" action="{{ route('password.confirm') }}" class="auth-form">
        @csrf

        <label for="password">
            Mật khẩu
            <input id="password" type="password" name="password" required autocomplete="current-password" autofocus>
            @error('password')<small>{{ $message }}</small>@enderror
        </label>

        <div class="auth-actions">
            <span></span>
            <button type="submit">Xác nhận <span aria-hidden="true">↗</span></button>
        </div>
    </form>
</x-guest-layout>
