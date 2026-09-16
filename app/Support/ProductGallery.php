<?php

namespace App\Support;

/**
 * Bộ ảnh mẫu móng trưng ra trang công khai.
 *
 * Nguồn ảnh là thư mục `public/images/products`, nhưng trang không đọc thẳng
 * thư mục đó: ảnh gốc nặng vài MB một tấm. `php artisan gallery:build` nén
 * sẵn ra bản WebP nhiều khổ kèm một manifest, và lớp này chỉ đọc manifest —
 * không quét thư mục, không đo lại kích thước ảnh trên mỗi lần vào trang.
 *
 * Chưa chạy lệnh thì manifest chưa có và trang tự ẩn khu trưng bày, thay vì
 * gửi cả trăm MB ảnh gốc xuống điện thoại khách.
 */
final class ProductGallery
{
    /** Khổ ảnh dựng sẵn: thẻ nhỏ trên điện thoại, thẻ lớn, và ảnh phóng to. */
    public const WIDTHS = [480, 960, 1440];

    public const QUALITY = 78;

    /** Đường dẫn công khai của thư mục ảnh đã nén, tính từ gốc `public`. */
    public const PUBLIC_PATH = 'images/products/web';

    public static function sourceDirectory(): string
    {
        return public_path('images/products');
    }

    public static function outputDirectory(): string
    {
        return public_path(self::PUBLIC_PATH);
    }

    public static function manifestPath(): string
    {
        return self::outputDirectory().DIRECTORY_SEPARATOR.'manifest.json';
    }

    /**
     * Toàn bộ ảnh đã nén, theo thứ tự tự nhiên của tên tệp gốc.
     *
     * @return list<array{slug: string, width: int, height: int, src: string, srcset: string}>
     */
    public static function all(): array
    {
        $path = self::manifestPath();

        if (! is_file($path)) {
            return [];
        }

        /** @var list<array{slug: string, width: int, height: int, sources: list<string>}>|null $entries */
        $entries = json_decode((string) file_get_contents($path), true);

        if (! is_array($entries)) {
            return [];
        }

        return array_values(array_map(self::present(...), $entries));
    }

    /**
     * Lấy một số ảnh đầu tiên, dùng cho những khu chỉ cần vài tấm điểm xuyết.
     *
     * @return list<array{slug: string, width: int, height: int, src: string, srcset: string}>
     */
    public static function take(int $limit): array
    {
        return array_slice(self::all(), 0, $limit);
    }

    /**
     * Dựng `srcset` để trình duyệt tự chọn khổ hợp với màn hình của khách.
     *
     * @param  array{slug: string, width: int, height: int, sources: list<string>}  $entry
     * @return array{slug: string, width: int, height: int, src: string, srcset: string}
     */
    private static function present(array $entry): array
    {
        $candidates = [];

        foreach ($entry['sources'] as $source) {
            $width = self::widthFromFilename($source);
            $candidates[] = asset($source).' '.$width.'w';
        }

        return [
            'slug' => $entry['slug'],
            'width' => $entry['width'],
            'height' => $entry['height'],
            'src' => asset($entry['sources'][count($entry['sources']) - 1]),
            'srcset' => implode(', ', $candidates),
        ];
    }

    /** Khổ ảnh nằm ngay trong tên tệp (`phonto-1-960.webp`), khỏi phải mở ảnh ra đo. */
    private static function widthFromFilename(string $source): int
    {
        preg_match('/-(\d+)\.webp$/', $source, $matches);

        return (int) ($matches[1] ?? 0);
    }
}
