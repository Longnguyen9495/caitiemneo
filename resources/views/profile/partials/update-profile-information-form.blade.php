<section class="account-section">
    <header>
        <h2>Thông tin hồ sơ</h2>
        <p>Cập nhật thông tin liên hệ, ảnh đại diện và tài khoản nhận lương của bạn.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="account-form" enctype="multipart/form-data">
        @csrf
        @method('patch')

        <div class="profile-avatar-field">
            @if ($user->avatar_path)
                <img class="profile-avatar" src="{{ Storage::disk('public')->url($user->avatar_path) }}" alt="Ảnh đại diện của {{ $user->name }}">
            @else
                <span class="profile-avatar profile-avatar--placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            @endif

            <label for="avatar">
                Ảnh đại diện
                <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" autocomplete="off">
                <small>JPG, PNG hoặc WebP, tối đa 2 MB.</small>
                @error('avatar')<small>{{ $message }}</small>@enderror
            </label>
        </div>

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

        <label for="phone">
            Số điện thoại
            <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" autocomplete="tel">
            @error('phone')<small>{{ $message }}</small>@enderror
        </label>

        <fieldset class="profile-bank-details">
            <legend>Thông tin nhận lương</legend>

            <div class="account-field">
                <label for="bank_name">Ngân hàng</label>
                <x-searchable-select
                    id="bank_name"
                    name="bank_name"
                    :options="\App\Support\BankDirectory::options()"
                    :value="old('bank_name', $user->bank_name)"
                    placeholder="Chọn ngân hàng"
                    search-placeholder="Gõ tên hoặc mã ngân hàng…"
                    empty-text="Không có ngân hàng nào khớp."
                />
                @error('bank_name')<small>{{ $message }}</small>@enderror
            </div>

            <label for="bank_account_holder">
                Tên chủ tài khoản
                <input id="bank_account_holder" name="bank_account_holder" type="text" value="{{ old('bank_account_holder', $user->bank_account_holder) }}" autocomplete="off">
                @error('bank_account_holder')<small>{{ $message }}</small>@enderror
            </label>

            <label for="bank_account_number">
                Số tài khoản
                <input id="bank_account_number" name="bank_account_number" type="text" inputmode="numeric" value="{{ old('bank_account_number', $user->bank_account_number) }}" autocomplete="off">
                @error('bank_account_number')<small>{{ $message }}</small>@enderror
            </label>
        </fieldset>

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
