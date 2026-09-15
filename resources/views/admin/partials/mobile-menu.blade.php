<div class="offcanvas offcanvas-start" tabindex="-1" id="neoMenu" aria-labelledby="neoMenuLabel">
    <div class="offcanvas-header pb-2">
        <h2 class="offcanvas-title neo-display fs-4 text-white mb-0" id="neoMenuLabel">Cái Tiệm Neo</h2>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Đóng menu"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column pt-0">
        <nav class="d-grid gap-1" aria-label="Toàn bộ menu quản trị">
            <x-admin.nav-items :items="$navigation" />
        </nav>

        <div class="mt-auto pt-3 border-top" style="border-color:rgba(255,255,255,.16)!important">
            <p class="mb-0 text-white fw-semibold">{{ $user->name }}</p>
            <p class="mb-3 small" style="color:#f4aac5">{{ $user->role->label() }}</p>

            <a href="{{ route('profile.edit') }}" @class(['neo-navlink mb-1', 'is-active' => request()->routeIs('profile.*')])>
                <x-admin.icon name="people" />
                <span>Tài khoản của tôi</span>
            </a>

            <a href="{{ route('home') }}" target="_blank" rel="noopener"
               class="neo-navlink mb-1">
                <x-admin.icon name="external" />
                <span>Xem website</span>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="neo-navlink w-100 border-0 bg-transparent text-start">
                    <x-admin.icon name="logout" />
                    <span>Đăng xuất</span>
                </button>
            </form>
        </div>
    </div>
</div>
