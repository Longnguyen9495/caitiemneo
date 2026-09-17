<x-guest-layout>
    <header class="auth-card-header">
        <p class="eyebrow">Khôi phục truy cập</p>
        <h2>Quên mật khẩu</h2>
        <p>Nhập email của tài khoản. Chúng tôi sẽ gửi cho bạn một liên kết để đặt lại mật khẩu mới.</p>
    </header>

    @if (session('status'))
        <div class="auth-alert auth-success" role="status">
            Chúng tôi đã gửi liên kết đặt lại mật khẩu tới email của bạn.
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
        @csrf

        <label for="email">
            Email
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email')<small>{{ $message }}</small>@enderror
        </label>

        <div class="auth-actions">
            <a href="{{ route('login') }}">← Quay lại đăng nhập</a>
            <button type="submit">Gửi liên kết <span aria-hidden="true">↗</span></button>
        </div>
    </form>
</x-guest-layout>
