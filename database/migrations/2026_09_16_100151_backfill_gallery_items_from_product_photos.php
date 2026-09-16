<?php

use App\Enums\GalleryMediaType;
use App\Support\ProductGallery;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Đưa bộ ảnh đã nén sẵn trong `public/images/products/web` vào album.
     *
     * Chạy một lần để album không trống lúc bàn giao; từ đây trở đi bảng
     * `gallery_items` là nguồn duy nhất của trang công khai, ảnh mới đi qua
     * trang quản trị.
     *
     * `created_at` lấy theo ngày sửa của ảnh gốc để bộ ảnh cũ nằm đúng dưới
     * những gì tiệm đăng sau này.
     */
    public function up(): void
    {
        $now = now();
        $rows = [];

        foreach (ProductGallery::manifestEntries() as $entry) {
            $sources = [];

            foreach ($entry['sources'] as $path) {
                preg_match('/-(\d+)\.webp$/', $path, $matches);
                $sources[(int) ($matches[1] ?? 0)] = $path;
            }

            if ($sources === []) {
                continue;
            }

            ksort($sources);
            $largest = $sources[array_key_last($sources)];
            $original = $this->originalFile($entry['slug']);

            $rows[] = [
                'type' => GalleryMediaType::Photo->value,
                'path' => $largest,
                'sources' => json_encode($sources),
                'width' => $entry['width'],
                'height' => $entry['height'],
                'original_name' => $original === null ? $entry['slug'] : basename($original),
                'byte_size' => $this->totalBytes($sources),
                'uploaded_by' => null,
                'created_at' => $original === null ? $now : date('Y-m-d H:i:s', filemtime($original)),
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('gallery_items')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('gallery_items')->whereNull('uploaded_by')->delete();
    }

    /** Ảnh gốc tương ứng với một slug, chỉ để lấy tên và ngày chụp. */
    private function originalFile(string $slug): ?string
    {
        foreach (glob(ProductGallery::sourceDirectory().DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file) && $this->slug(basename($file)) === $slug) {
                return $file;
            }
        }

        return null;
    }

    private function slug(string $name): string
    {
        $name = pathinfo($name, PATHINFO_FILENAME);

        return trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? ''), '-');
    }

    /** @param  array<int, string>  $sources */
    private function totalBytes(array $sources): int
    {
        $total = 0;

        foreach ($sources as $path) {
            $file = public_path($path);

            if (is_file($file)) {
                $total += filesize($file);
            }
        }

        return $total;
    }
};
