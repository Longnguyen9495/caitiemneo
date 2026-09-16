<?php

namespace App\Enums;

/**
 * Loại tệp trong album của tiệm.
 *
 * Ảnh được nén sẵn thành nhiều khổ WebP, video thì giữ nguyên tệp gốc vì máy
 * chủ không có ffmpeg để chuyển mã.
 */
enum GalleryMediaType: string
{
    case Photo = 'photo';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Ảnh',
            self::Video => 'Video',
        };
    }
}
