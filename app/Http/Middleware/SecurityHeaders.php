<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response headers that limit the damage of a mistake elsewhere.
 *
 * None of these stops an attack by itself; each narrows what a successful one
 * could do. They are applied centrally rather than per route so a new page
 * cannot be added without them.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ($this->headers() as $name => $value) {
            // Không ghi đè nếu một tầng khác đã đặt header này.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            // Trình duyệt không được tự đoán kiểu nội dung: một file CSV xuất ra
            // không được phép biến thành thứ chạy được.
            'X-Content-Type-Options' => 'nosniff',

            // Không cho nhúng trang quản trị vào khung của người khác để lừa bấm.
            'X-Frame-Options' => 'DENY',

            // Gửi đủ nguồn gốc khi ra ngoài, nhưng không kèm đường dẫn — URL của
            // khu quản trị có thể chứa mã hóa đơn, mã nhân sự.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',

            // Định vị chỉ dùng cho chính trang này (nút Vào ca), và không cho
            // mượn máy ảnh hay micro.
            'Permissions-Policy' => 'geolocation=(self), camera=(), microphone=(), payment=(), usb=()',

            'Content-Security-Policy' => $this->contentSecurityPolicy(),
        ];
    }

    /**
     * Nguồn nội dung được phép tải.
     *
     * Vài ghi chú để người sau không nới lỏng nhầm:
     *
     * - `script-src` **không** có `unsafe-inline` vì toàn bộ JS đi qua Vite,
     *   không có thẻ `<script>` viết thẳng trong Blade. Giữ được như vậy thì
     *   chính sách này mới thực sự chặn được script chèn vào.
     * - `unsafe-eval` là cái giá của Alpine: nó biên dịch biểu thức trong
     *   thuộc tính `x-*` lúc chạy. Bỏ được nếu sau này chuyển sang bản Alpine
     *   dựng sẵn (CSP build).
     * - `style-src` có `unsafe-inline` vì còn khoảng 25 thuộc tính `style=""`
     *   trong Blade. Dọn hết chúng thì siết lại được.
     * - Font tải từ Google nên phải khai báo; tự host sẽ gọn hơn nhưng đó là
     *   thay đổi khác.
     */
    private function contentSecurityPolicy(): string
    {
        // Khi chạy `npm run dev`, Vite phục vụ asset và websocket HMR từ một
        // cổng khác, nên môi trường local cần mở thêm đúng phần đó. Production
        // không bao giờ đi vào nhánh này.
        $vite = app()->environment('local') ? ' http://localhost:* ws://localhost:*' : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval'".$vite,
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com".$vite,
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data:",
            "connect-src 'self'".$vite,
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
        ]);
    }
}
