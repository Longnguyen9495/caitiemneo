<?php

namespace App\Http\Requests\Admin;

use App\Models\GalleryItem;
use Illuminate\Foundation\Http\FormRequest;

class GalleryUploadRequest extends FormRequest
{
    /**
     * Mỗi tệp tối đa 37 MB.
     *
     * Đây là trần của PHP (`post_max_size`) trừ đi chỗ cho phần còn lại của
     * biểu mẫu, chứ không phải con số tự nghĩ ra: vượt trần thì PHP cắt yêu cầu
     * trước khi Laravel kịp đọc, và khách chỉ thấy một lỗi 413 khó hiểu.
     */
    public const MAX_KILOBYTES = 37888;

    public const MAX_FILES = 5;

    public function authorize(): bool
    {
        return $this->user()->can('create', GalleryItem::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
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

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
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
