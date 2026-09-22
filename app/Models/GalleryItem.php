<?php

namespace App\Models;

use App\Enums\GalleryAlbum;
use App\Enums\GalleryMediaType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một tấm ảnh hoặc một video trong album của tiệm.
 *
 * Mọi đường dẫn lưu ở đây tính từ gốc `public`, nên `asset()` dùng được ngay
 * cho cả tệp mới tải lên lẫn bộ ảnh nén sẵn từ thư mục products.
 */
#[Fillable([
    'type',
    'album',
    'path',
    'sources',
    'width',
    'height',
    'original_name',
    'byte_size',
    'uploaded_by',
])]
class GalleryItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => GalleryMediaType::class,
            'album' => GalleryAlbum::class,
            'sources' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'byte_size' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Mới đăng lên thì đứng đầu album.
     *
     * Kèm khoá chính để hai tệp tải lên trong cùng một giây vẫn có thứ tự cố
     * định, tránh album tự nháy đổi chỗ giữa các lần tải trang.
     */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function scopeOfType(Builder $query, GalleryMediaType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Tệp thuộc một khu của trang công khai.
     *
     * Mọi truy vấn cho trang công khai phải đi qua đây: thiếu nó thì ảnh chụp
     * tin nhắn khách khen sẽ nằm lẫn trong lưới mẫu móng.
     */
    public function scopeInAlbum(Builder $query, GalleryAlbum $album): Builder
    {
        return $query->where('album', $album);
    }

    public function isPhoto(): bool
    {
        return $this->type === GalleryMediaType::Photo;
    }

    public function url(): string
    {
        return asset($this->path);
    }

    /**
     * Danh sách khổ ảnh cho thuộc tính `srcset`.
     *
     * Video không có nhiều khổ nên trả về chuỗi rỗng, và thẻ <video> cũng không
     * dùng tới.
     */
    public function srcset(): string
    {
        $candidates = [];

        foreach ($this->sources ?? [] as $width => $path) {
            $candidates[] = asset($path).' '.$width.'w';
        }

        return implode(', ', $candidates);
    }

    /** Ảnh khổ nhỏ nhất, dùng cho lưới xem lại trong khu quản trị. */
    public function thumbnailUrl(): string
    {
        $sources = $this->sources ?? [];

        return asset($sources === [] ? $this->path : $sources[array_key_first($sources)]);
    }

    /** Mọi tệp thuộc về mục này, để xoá sạch khi gỡ khỏi album. */
    public function filePaths(): array
    {
        return array_values(array_unique(array_merge([$this->path], array_values($this->sources ?? []))));
    }
}
