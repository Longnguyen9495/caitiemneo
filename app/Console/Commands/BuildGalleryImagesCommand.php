<?php

namespace App\Console\Commands;

use App\Support\ProductGallery;
use Illuminate\Console\Command;

/**
 * Nén ảnh mẫu móng trong `public/images/products` thành bản dùng được trên web.
 *
 * Ảnh gốc là ảnh chụp thẳng từ iPhone: 3–8 MB một tấm và xoay theo cờ EXIF.
 * Bê nguyên lên trang công khai thì một lần mở trang trên 4G là hơn 100 MB,
 * nên mọi tấm được xoay đúng chiều, thu nhỏ về vài khổ và ghi ra WebP. Trang
 * chỉ đọc thư mục kết quả, không bao giờ trỏ vào ảnh gốc.
 *
 * Lệnh chạy lại được: ảnh nào đã có bản mới hơn ảnh gốc thì bỏ qua, trừ khi
 * truyền --force.
 */
class BuildGalleryImagesCommand extends Command
{
    protected $signature = 'gallery:build {--force : Dựng lại cả những ảnh đã có bản web}';

    protected $description = 'Nén ảnh mẫu móng thành bản WebP nhiều khổ cho trang công khai';

    /** Ảnh HEIC của iPhone không hiển thị được trên Chrome và GD cũng không đọc nổi. */
    private const READABLE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('Thiếu phần mở rộng GD của PHP nên không nén được ảnh.');

            return self::FAILURE;
        }

        $sources = $this->sourceFiles();

        if ($sources === []) {
            $this->error('Không tìm thấy ảnh nào trong '.ProductGallery::sourceDirectory());

            return self::FAILURE;
        }

        $this->ensureOutputDirectory();

        $manifest = [];
        $skipped = [];
        $built = 0;

        foreach ($sources as $source) {
            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));

            if (! in_array($extension, self::READABLE_EXTENSIONS, true)) {
                $skipped[] = basename($source);

                continue;
            }

            $entry = $this->buildEntry($source, $built);

            if ($entry === null) {
                $skipped[] = basename($source);

                continue;
            }

            $manifest[] = $entry;
        }

        file_put_contents(
            ProductGallery::manifestPath(),
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        $this->newLine();
        $this->info(count($manifest).' ảnh đã có bản web trong '.ProductGallery::outputDirectory().' ('.$built.' ảnh vừa dựng lại).');

        if ($skipped !== []) {
            $this->warn('Bỏ qua '.count($skipped).' tệp không đọc được: '.implode(', ', $skipped));
            $this->line('Hãy xuất các tệp HEIC sang JPG rồi chạy lại lệnh.');
        }

        return self::SUCCESS;
    }

    /**
     * Ảnh gốc theo thứ tự tự nhiên, để lần chạy nào cũng cho ra cùng một thứ tự trưng bày.
     *
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = glob(ProductGallery::sourceDirectory().DIRECTORY_SEPARATOR.'*') ?: [];
        $files = array_values(array_filter($files, is_file(...)));

        natcasesort($files);

        return array_values($files);
    }

    private function ensureOutputDirectory(): void
    {
        $directory = ProductGallery::outputDirectory();

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }
    }

    /**
     * Dựng mọi khổ của một ảnh và trả về dòng tương ứng trong manifest.
     *
     * @return array{slug: string, width: int, height: int, sources: array<int, string>}|null
     */
    private function buildEntry(string $source, int &$built): ?array
    {
        $slug = $this->slug($source);
        $dimensions = @getimagesize($source);

        if ($dimensions === false) {
            return null;
        }

        $orientation = $this->orientation($source);
        [$sourceWidth, $sourceHeight] = $this->orientedSize($dimensions[0], $dimensions[1], $orientation);

        $widths = array_values(array_filter(
            ProductGallery::WIDTHS,
            fn (int $width): bool => $width <= $sourceWidth,
        ));

        if ($widths === []) {
            $widths = [$sourceWidth];
        }

        $targets = [];

        foreach ($widths as $width) {
            $targets[$width] = ProductGallery::outputDirectory().DIRECTORY_SEPARATOR.$slug.'-'.$width.'.webp';
        }

        if ($this->needsRebuild($source, $targets)) {
            $image = $this->readImage($source, $dimensions[2]);

            if ($image === null) {
                return null;
            }

            $image = $this->applyOrientation($image, $orientation);

            foreach ($targets as $width => $target) {
                $this->writeResized($image, $width, $sourceWidth, $sourceHeight, $target);
            }

            imagedestroy($image);
            $built++;
            $this->line('  <fg=green>✓</> '.basename($source).' → '.count($targets).' khổ');
        }

        $largest = max($widths);

        return [
            'slug' => $slug,
            'width' => $largest,
            'height' => (int) round($largest * $sourceHeight / $sourceWidth),
            'sources' => array_map(
                fn (int $width): string => ProductGallery::PUBLIC_PATH.'/'.$slug.'-'.$width.'.webp',
                $widths,
            ),
        ];
    }

    /**
     * @param  array<int, string>  $targets
     */
    private function needsRebuild(string $source, array $targets): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $sourceTime = filemtime($source);

        foreach ($targets as $target) {
            if (! is_file($target) || filemtime($target) < $sourceTime) {
                return true;
            }
        }

        return false;
    }

    /** Tên tệp an toàn cho URL: `phonto(10).jpg` thành `phonto-10`. */
    private function slug(string $source): string
    {
        $name = pathinfo($source, PATHINFO_FILENAME);
        $name = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '');

        return trim($name, '-');
    }

    /** @return \GdImage|null */
    private function readImage(string $source, int $type): ?object
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
            default => false,
        };

        return $image === false ? null : $image;
    }

    /**
     * Cờ xoay EXIF của iPhone: ảnh nằm ngang trong tệp nhưng phải hiện dọc.
     *
     * Trình duyệt tự tôn trọng cờ này, GD thì không, nên bỏ qua bước xoay là
     * cả nửa bộ ảnh nằm ngửa.
     */
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
        imagewebp($canvas, $target, ProductGallery::QUALITY);
        imagedestroy($canvas);
    }
}
