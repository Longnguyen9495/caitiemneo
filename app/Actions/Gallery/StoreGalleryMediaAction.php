<?php

namespace App\Actions\Gallery;

use App\Enums\GalleryAlbum;
use App\Enums\GalleryMediaType;
use App\Models\GalleryItem;
use App\Models\User;
use App\Services\Gallery\PhotoResizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Nhận một tệp tiệm vừa tải lên và đưa nó vào album.
 *
 * Ảnh được nén ngay tại đây thành các khổ WebP rồi bỏ tệp gốc, vì trang công
 * khai không bao giờ cần tới ảnh 8 MB. Video giữ nguyên: máy chủ không có
 * ffmpeg nên chuyển mã là không làm được, chỉ giới hạn dung lượng lúc nhận.
 */
class StoreGalleryMediaAction
{
    /** Thư mục con trong đĩa `public`, xem được qua liên kết `public/storage`. */
    private const DIRECTORY = 'gallery';

    public function __construct(private readonly PhotoResizer $resizer) {}

    public function handle(UploadedFile $file, User $actor, GalleryAlbum $album): ?GalleryItem
    {
        return $this->isVideo($file)
            ? $this->storeVideo($file, $actor, $album)
            : $this->storePhoto($file, $actor, $album);
    }

    private function storePhoto(UploadedFile $file, User $actor, GalleryAlbum $album): ?GalleryItem
    {
        $result = $this->resizer->write(
            $file->getRealPath(),
            $this->absoluteDirectory(),
            $this->publicPrefix(),
            $this->uniqueSlug($file),
        );

        if ($result === null) {
            return null;
        }

        $sources = $result['sources'];

        return GalleryItem::query()->create([
            'type' => GalleryMediaType::Photo,
            'album' => $album,
            'path' => $sources[array_key_last($sources)],
            'sources' => $sources,
            'width' => $result['width'],
            'height' => $result['height'],
            'original_name' => $file->getClientOriginalName(),
            'byte_size' => $this->totalBytes($sources),
            'uploaded_by' => $actor->getKey(),
        ]);
    }

    private function storeVideo(UploadedFile $file, User $actor, GalleryAlbum $album): GalleryItem
    {
        $name = $this->uniqueSlug($file).'.'.strtolower($file->getClientOriginalExtension());
        $file->storeAs(self::DIRECTORY, $name, 'public');

        return GalleryItem::query()->create([
            'type' => GalleryMediaType::Video,
            'album' => $album,
            'path' => $this->publicPrefix().'/'.$name,
            'sources' => null,
            'width' => null,
            'height' => null,
            'original_name' => $file->getClientOriginalName(),
            'byte_size' => $file->getSize(),
            'uploaded_by' => $actor->getKey(),
        ]);
    }

    private function isVideo(UploadedFile $file): bool
    {
        return str_starts_with((string) $file->getMimeType(), 'video/');
    }

    /**
     * Tên tệp giữ lại phần tên gốc cho dễ nhận ra, cộng một đuôi ngẫu nhiên để
     * hai lần đăng cùng một tấm không đè lên nhau.
     */
    private function uniqueSlug(UploadedFile $file): string
    {
        $base = $this->resizer->slug($file->getClientOriginalName());

        return ($base === '' ? 'anh' : $base).'-'.Str::lower(Str::random(6));
    }

    /**
     * Hỏi chính đĩa `public` xem thư mục nằm ở đâu, thay vì tự ghép
     * `storage_path()`: nhờ vậy `Storage::fake()` trong kiểm thử chuyển hướng
     * được cả phần ghi ảnh, không rải tệp rác vào thư mục thật.
     */
    private function absoluteDirectory(): string
    {
        return Storage::disk('public')->path(self::DIRECTORY);
    }

    private function publicPrefix(): string
    {
        return 'storage/'.self::DIRECTORY;
    }

    /** @param  array<int, string>  $sources */
    private function totalBytes(array $sources): int
    {
        $total = 0;

        foreach ($sources as $path) {
            $file = $this->absoluteDirectory().DIRECTORY_SEPARATOR.basename($path);

            if (is_file($file)) {
                $total += filesize($file);
            }
        }

        return $total;
    }
}
