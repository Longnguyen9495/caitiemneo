{{--
    Các phiên đăng nhập đang mở của chính tài khoản này.

    Mục đích là để người dùng nhận ra một thiết bị lạ ngay, thay vì chỉ biết
    sau khi đã xảy ra chuyện. Chỉ hiện phiên của chính mình, không hiện của
    người khác.
--}}
<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Phiên đăng nhập</h2>

        <p class="mt-1 text-sm text-gray-600">
            Những thiết bị đang đăng nhập vào tài khoản của bạn. Nếu thấy thiết bị lạ,
            hãy kết thúc các phiên khác rồi đổi mật khẩu.
        </p>
    </header>

    @if (empty($sessions))
        <p class="mt-4 text-sm text-gray-600">
            Không đọc được danh sách phiên. Điều này bình thường nếu hệ thống đang lưu phiên ngoài cơ sở dữ liệu.
        </p>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($sessions as $session)
                <li class="text-sm text-gray-700">
                    <span class="font-medium">
                        {{ $session['ip_address'] ?? 'Không rõ địa chỉ' }}
                        @if ($session['is_current'])
                            <span class="ml-1 text-green-600">· Phiên hiện tại</span>
                        @endif
                    </span>
                    <span class="block text-gray-500">
                        Hoạt động lần cuối {{ $session['last_active_at']->diffForHumans() }}
                        @if ($session['user_agent'])
                            · {{ \Illuminate\Support\Str::limit($session['user_agent'], 60) }}
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>

        <form method="POST" action="{{ route('profile.sessions.revoke') }}" class="mt-4">
            @csrf
            @method('DELETE')

            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                Kết thúc các phiên khác
            </button>
        </form>
    @endif
</section>
