<section class="account-section">
    <header>
        <h2>Đổi mật khẩu</h2>
        <p>Dùng mật khẩu mạnh, riêng tư để bảo vệ tài khoản quản trị của bạn.</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="account-form">
        @csrf
        @method('put')

        <label for="update_password_current_password">
            Mật khẩu hiện tại
            <input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password">
            @error('current_password', 'updatePassword')<small>{{ $message }}</small>@enderror
        </label>

        <label for="update_password_password">
            Mật khẩu mới
            <input id="update_password_password" name="password" type="password" autocomplete="new-password">
            @error('password', 'updatePassword')<small>{{ $message }}</small>@enderror
        </label>

        <label for="update_password_password_confirmation">
            Xác nhận mật khẩu mới
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
            @error('password_confirmation', 'updatePassword')<small>{{ $message }}</small>@enderror
        </label>

        <div class="account-form-actions">
            <button class="button button-primary" type="submit">Cập nhật mật khẩu</button>
            @if (session('status') === 'password-updated')
                <p class="account-success" x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)">Đã cập nhật.</p>
            @endif
        </div>
    </form>
</section>
