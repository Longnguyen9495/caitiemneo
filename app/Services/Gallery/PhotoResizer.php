<?php

namespace App\Services\Gallery;

/**
 * Nén một tấm ảnh thành vài khổ WebP dùng được trên web.
 *
 * Ảnh nguồn là ảnh chụp thẳng từ điện thoại: vài MB một tấm và xoay theo cờ
 * EXIF. Trình duyệt tôn trọng cờ đó, GD thì không, nên bước xoay phải làm bằng
 * tay — thiếu nó là nửa bộ ảnh nằm ngửa.
 *
 * Dùng chung cho cả lệnh nén thư mục ảnh cũ lẫn luồng tải ảnh mới từ trang
 * quản trị, để hai đường cho ra cùng một kết quả.
 */
class PhotoResizer
{
    /** Khổ ảnh dựng sẵn: thẻ nhỏ trên điện thoại, thẻ lớn, và ảnh phóng to. */
    public const WIDTHS = [480, 960, 1440];

    public const QUALITY = 78;

    /** GD đọc được ngần này; HEIC của iPhone thì không. */
    public const READABLE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Ghi các khổ ảnh và trả về đường dẫn công khai của từng khổ.
     *
     * @param  string  $sourceFile  Đường dẫn tuyệt đối tới ảnh gốc.
     * @param  string  $directory  Thư mục tuyệt đối để ghi kết quả.
     * @param  string  $publicPrefix  Tiền tố đường dẫn tính từ gốc `public`.
     * @return array{sources: array<int, string>, width: int, height: int}|null
     */
    public function write(string $sourceFile, string $directory, string $publicPrefix, string $slug): ?array
    {
        $dimensions = @getimagesize($sourceFile);

        if ($dimensions === false) {
            return null;
        }

        $image = $this->read($sourceFile, $dimensions[2]);

        if ($image === null) {
            return null;
        }

        $orientation = $this->orientation($sourceFile);
        $image = $this->applyOrientation($image, $orientation);
        [$sourceWidth, $sourceHeight] = $this->orientedSize($dimensions[0], $dimensions[1], $orientation);

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        $sources = [];

        foreach ($this->widthsFor($sourceWidth) as $width) {
            $name = $slug.'-'.$width.'.webp';

            $this->writeResized($image, $width, $sourceWidth, $sourceHeight, $directory.DIRECTORY_SEPARATOR.$name);

            $sources[$width] = trim($publicPrefix, '/').'/'.$name;
        }

        imagedestroy($image);

        $largest = max(array_keys($sources));

        return [
            'sources' => $sources,
            'width' => $largest,
            'height' => (int) round($largest * $sourceHeight / $sourceWidth),
        ];
    }

    /**
     * Không phóng to ảnh nhỏ hơn khổ yêu cầu: làm vậy chỉ tổ nặng tệp mà ảnh
     * vẫn nhoè đúng như cũ.
     *
     * @return list<int>
     */
    public function widthsFor(int $sourceWidth): array
    {
        $widths = array_values(array_filter(self::WIDTHS, fn (int $width): bool => $width <= $sourceWidth));

        return $widths === [] ? [$sourceWidth] : $widths;
    }

    /** Tên tệp an toàn cho URL: `phonto(10).jpg` thành `phonto-10`. */
    public function slug(string $name): string
    {
        $name = pathinfo($name, PATHINFO_FILENAME);
        $name = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '');

        return trim($name, '-');
    }

    /** @return \GdImage|null */
    private function read(string $source, int $type): ?object
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
            default => false,
        };

        return $image === false ? null : $image;
    }

    private function orientation(string $source): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($source);

        return (int) ($exif['Orientation'] ?? 1);
    }

    /** @return array{0: int, 1: int} */
    private function orientedSize(int $width, int $height, int $orientation): array
    {
        return in_array($orientation, [5, 6, 7, 8], true) ? [$height, $width] : [$width, $height];
    }

    /**
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private function applyOrientation(object $image, int $orientation): object
    {
        $rotation = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        if ($rotation === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $rotation, 0);
        imagedestroy($image);

        return $rotated;
    }

    /** @param  \GdImage  $image */
    private function writeResized(object $image, int $width, int $sourceWidth, int $sourceHeight, string $target): void
    {
        $height = max(1, (int) round($width * $sourceHeight / $sourceWidth));
        $canvas = imagecreatetruecolor($width, $height);

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        imagewebp($canvas, $target, self::QUALITY);
        imagedestroy($canvas);
    }
}
