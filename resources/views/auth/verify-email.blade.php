<x-guest-layout>
    <header class="auth-card-header">
        <p class="eyebrow">Xác thực tài khoản</p>
        <h2>Xác minh email</h2>
        <p>Cảm ơn bạn đã đăng ký. Vui lòng bấm vào liên kết trong email chúng tôi vừa gửi để xác minh địa chỉ email. Nếu chưa nhận được, bạn có thể yêu cầu gửi lại.</p>
    </header>

    @if (session('status') == 'verification-link-sent')
        <div class="auth-alert auth-success" role="status">
            Một liên kết xác minh mới đã được gửi tới email bạn đăng ký.
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="auth-form">
        @csrf
        <div class="auth-actions">
            <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('verify-logout-form').submit();">Đăng xuất</a>
            <button type="submit">Gửi lại email <span aria-hidden="true">↗</span></button>
        </div>
    </form>

    <form id="verify-logout-form" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
</x-guest-layout>
