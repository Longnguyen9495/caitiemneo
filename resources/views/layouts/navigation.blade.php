<nav class="account-nav" aria-label="Điều hướng tài khoản">
    <div class="account-container account-nav-inner">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark">N</span>
            Cái Tiệm Neo
        </a>

        <div class="account-nav-links">
            <a @class(['is-active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}">Quản trị</a>
            <a @class(['is-active' => request()->routeIs('profile.*')]) href="{{ route('profile.edit') }}">Tài khoản</a>
            <span class="account-user">{{ Auth::user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Đăng xuất</button>
            </form>
        </div>
    </div>
</nav>
