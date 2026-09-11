<section class="account-section">
    <header>
        <h2>Thông tin hồ sơ</h2>
        <p>Cập nhật tên hiển thị và địa chỉ email dùng cho tài khoản của bạn.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="account-form">
        @csrf
        @method('patch')

        <label for="name">
            Họ và tên
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
            @error('name')<small>{{ $message }}</small>@enderror
        </label>

        <label for="email">
            Email
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
            @error('email')<small>{{ $message }}</small>@enderror
        </label>

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="account-notice">
                <p>Email của bạn chưa được xác thực.</p>
                <button class="account-text-button" form="send-verification" type="submit">Gửi lại email xác thực</button>

                @if (session('status') === 'verification-link-sent')
                    <p class="account-success">Đã gửi email xác thực mới.</p>
                @endif
            </div>
        @endif

        <div class="account-form-actions">
            <button class="button button-primary" type="submit">Lưu thay đổi</button>
            @if (session('status') === 'profile-updated')
                <p class="account-success" x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)">Đã lưu.</p>
            @endif
        </div>
    </form>
</section>
