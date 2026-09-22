<?php

namespace App\Enums;

/**
 * Tệp trong album thuộc về khu nào của trang công khai.
 *
 * Hai khu này không được lẫn vào nhau: ảnh chụp tin nhắn khách khen không phải
 * một mẫu móng để khách khác chọn làm, và mẫu móng cũng không phải lời khen.
 */
enum GalleryAlbum: string
{
    case Showcase = 'showcase';
    case Feedback = 'feedback';

    public function label(): string
    {
        return match ($this) {
            self::Showcase => 'Mẫu móng',
            // Kèm "ảnh/video" để không lẫn với hàng đợi feedback khách tự gõ
            // ở màn hình bên cạnh.
            self::Feedback => 'Feedback ảnh/video',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Showcase => 'Ảnh và video mẫu móng tiệm đã làm. Hiện ở trang chủ và trang album.',
            self::Feedback => 'Ảnh chụp tin nhắn khách khen, video khách review. Hiện ở khu "Khách nói gì" trên trang chủ.',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
