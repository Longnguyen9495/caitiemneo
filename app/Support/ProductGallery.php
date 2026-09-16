<?php

namespace App\Support;

/**
 * Bộ ảnh mẫu móng có sẵn trong `public/images/products`.
 *
 * Đây chỉ còn là kho ảnh ban đầu: `php artisan gallery:build` nén chúng ra
 * WebP nhiều khổ kèm một manifest, rồi một migration đưa manifest đó vào bảng
 * `gallery_items`. Trang công khai đọc bảng, không đọc thư mục này nữa.
 */
final class ProductGallery
{
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
     * Các dòng trong manifest, theo thứ tự lệnh nén đã ghi ra.
     *
     * @return list<array{slug: string, width: int, height: int, sources: list<string>}>
     */
    public static function manifestEntries(): array
    {
        $path = self::manifestPath();

        if (! is_file($path)) {
            return [];
        }

        $entries = json_decode((string) file_get_contents($path), true);

        return is_array($entries) ? array_values($entries) : [];
    }
}
