<?php

namespace App\Console\Commands;

use App\Services\Gallery\PhotoResizer;
use App\Support\ProductGallery;
use Illuminate\Console\Command;

/**
 * Nén bộ ảnh mẫu móng có sẵn trong `public/images/products` thành bản web.
 *
 * Đây là lệnh dựng bộ ảnh ban đầu, chạy một lần lúc bàn giao. Ảnh đăng thêm về
 * sau đi qua trang quản trị, không đổ tệp thẳng vào thư mục này nữa.
 *
 * Lệnh chạy lại được: ảnh nào đã có bản mới hơn ảnh gốc thì bỏ qua, trừ khi
 * truyền --force.
 */
class BuildGalleryImagesCommand extends Command
{
    protected $signature = 'gallery:build {--force : Dựng lại cả những ảnh đã có bản web}';

    protected $description = 'Nén ảnh mẫu móng thành bản WebP nhiều khổ cho trang công khai';

    public function __construct(private readonly PhotoResizer $resizer)
    {
        parent::__construct();
    }

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

        $manifest = [];
        $skipped = [];
        $built = 0;

        foreach ($sources as $source) {
            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));

            if (! in_array($extension, PhotoResizer::READABLE_EXTENSIONS, true)) {
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
     * Ảnh gốc theo thứ tự tự nhiên, để lần chạy nào cũng cho ra cùng một thứ tự.
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

    /**
     * @return array{slug: string, width: int, height: int, sources: array<int, string>}|null
     */
    private function buildEntry(string $source, int &$built): ?array
    {
        $slug = $this->resizer->slug(basename($source));
        $dimensions = @getimagesize($source);

        if ($dimensions === false) {
            return null;
        }

        $widths = $this->resizer->widthsFor(max($dimensions[0], $dimensions[1]));

        if (! $this->needsRebuild($source, $slug, $widths)) {
            return $this->entryFromDisk($slug, $source);
        }

        $result = $this->resizer->write(
            $source,
            ProductGallery::outputDirectory(),
            ProductGallery::PUBLIC_PATH,
            $slug,
        );

        if ($result === null) {
            return null;
        }

        $built++;
        $this->line('  <fg=green>✓</> '.basename($source).' → '.count($result['sources']).' khổ');

        return [
            'slug' => $slug,
            'width' => $result['width'],
            'height' => $result['height'],
            'sources' => array_values($result['sources']),
        ];
    }

    /**
     * @param  list<int>  $widths
     */
    private function needsRebuild(string $source, string $slug, array $widths): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $sourceTime = filemtime($source);

        foreach ($widths as $width) {
            $target = ProductGallery::outputDirectory().DIRECTORY_SEPARATOR.$slug.'-'.$width.'.webp';

            if (! is_file($target) || filemtime($target) < $sourceTime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dòng manifest cho ảnh đã có sẵn bản web, đọc kích thước từ chính tệp đã nén.
     *
     * @return array{slug: string, width: int, height: int, sources: array<int, string>}|null
     */
    private function entryFromDisk(string $slug, string $source): ?array
    {
        $existing = glob(ProductGallery::outputDirectory().DIRECTORY_SEPARATOR.$slug.'-*.webp') ?: [];

        if ($existing === []) {
            return null;
        }

        sort($existing, SORT_NATURAL);
        $largest = $existing[count($existing) - 1];
        $dimensions = @getimagesize($largest);

        if ($dimensions === false) {
            return null;
        }

        return [
            'slug' => $slug,
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'sources' => array_map(
                fn (string $file): string => ProductGallery::PUBLIC_PATH.'/'.basename($file),
                $existing,
            ),
        ];
    }
}
