<section class="account-section account-danger-section">
    <header>
        <h2>Xóa tài khoản</h2>
        <p>Thao tác này sẽ xóa vĩnh viễn toàn bộ dữ liệu gắn với tài khoản. Hãy nhập mật khẩu để xác nhận.</p>
    </header>

    <form method="post" action="{{ route('profile.destroy') }}" class="account-form">
        @csrf
        @method('delete')

        <label for="password">
            Mật khẩu hiện tại
            <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu để xác nhận">
            @error('password', 'userDeletion')<small>{{ $message }}</small>@enderror
        </label>

        <div class="account-form-actions">
            <button class="account-danger-button" type="submit" onclick="return confirm('Bạn có chắc muốn xóa vĩnh viễn tài khoản này không?');">Xóa tài khoản</button>
        </div>
    </form>
</section>
