<?php

namespace App\Http\Requests\Admin;

use App\Enums\GalleryAlbum;
use App\Models\GalleryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GalleryUploadRequest extends FormRequest
{
    /**
     * Mỗi tệp tối đa 9 MB, thấp hơn `upload_max_filesize = 10M` trên production
     * để chừa phần chênh lệch đơn vị và metadata multipart. Tổng request cho 5
     * tệp cần được Nginx/PHP cho phép ít nhất 48 MB.
     */
    public const MAX_KILOBYTES = 9216;

    public const MAX_FILES = 5;

    public function authorize(): bool
    {
        return $this->user()->can('create', GalleryItem::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            // Bắt buộc, không mặc định: nhận thiếu khóa này mà lặng lẽ coi là
            // "mẫu móng" thì một hôm nào đó ảnh chụp tin nhắn khách sẽ nằm giữa
            // lưới mẫu để khách khác chọn làm.
            'album' => ['required', Rule::enum(GalleryAlbum::class)],
            'media' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
            'media.*' => [
                'file',
                // Kiểm theo nội dung tệp chứ không theo đuôi. HEIC của iPhone cố
                // tình không nằm trong danh sách: GD không đọc được nên có nhận
                // cũng chỉ ra một ảnh hỏng.
                'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm',
                'max:'.self::MAX_KILOBYTES,
            ],
        ];
    }

    /** Khu của trang công khai mà những tệp này sẽ hiện ra. */
    public function album(): GalleryAlbum
    {
        return $this->enum('album', GalleryAlbum::class) ?? GalleryAlbum::Showcase;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'album' => 'khu hiển thị',
            'media' => 'tệp tải lên',
            'media.*' => 'tệp tải lên',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'media.*.mimetypes' => 'Chỉ nhận ảnh JPG, PNG, WebP và video MP4, MOV, WebM. Ảnh HEIC của iPhone cần xuất sang JPG trước.',
            'media.*.max' => 'Mỗi tệp tối đa '.round(self::MAX_KILOBYTES / 1024).' MB.',
        ];
    }
}
