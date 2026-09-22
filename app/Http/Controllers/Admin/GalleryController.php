<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Gallery\StoreGalleryMediaAction;
use App\Enums\GalleryAlbum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GalleryUploadRequest;
use App\Models\GalleryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GalleryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', GalleryItem::class);

        // Mỗi khu là một danh sách riêng: trộn chúng vào một trang thì người
        // đăng không còn thấy rõ tệp mình vừa tải lên sẽ hiện ở đâu.
        $album = $request->enum('album', GalleryAlbum::class) ?? GalleryAlbum::Showcase;

        return view('admin.gallery.index', [
            'items' => GalleryItem::query()
                ->with('uploader')
                ->inAlbum($album)
                ->newestFirst()
                ->paginate(24)
                ->withQueryString(),
            'album' => $album,
            'albums' => GalleryAlbum::cases(),
            'counts' => GalleryItem::query()
                ->selectRaw('album, count(*) as total')
                ->groupBy('album')
                ->pluck('total', 'album'),
            'maxFiles' => GalleryUploadRequest::MAX_FILES,
            'maxMegabytes' => (int) round(GalleryUploadRequest::MAX_KILOBYTES / 1024),
        ]);
    }

    public function store(GalleryUploadRequest $request, StoreGalleryMediaAction $action): RedirectResponse
    {
        $album = $request->album();
        $stored = 0;
        $failed = [];

        foreach ($request->file('media') as $file) {
            if ($action->handle($file, $request->user(), $album) === null) {
                $failed[] = $file->getClientOriginalName();

                continue;
            }

            $stored++;
        }

        $redirect = redirect()->route('admin.gallery.index', ['album' => $album->value]);

        if ($failed !== []) {
            return $redirect->with('error', 'Không đọc được '.count($failed).' tệp: '.implode(', ', $failed));
        }

        return $redirect->with('success', 'Đã đăng '.$stored.' tệp lên album.');
    }

    public function destroy(GalleryItem $gallery): RedirectResponse
    {
        $this->authorize('delete', $gallery);

        $this->deleteUploadedFiles($gallery);
        $gallery->delete();

        return redirect()->route('admin.gallery.index', ['album' => $gallery->album->value])
            ->with('success', 'Đã gỡ khỏi album.');
    }

    /**
     * Chỉ xoá tệp do tiệm tự tải lên.
     *
     * Bộ ảnh ban đầu nằm trong `public/images/products/web` và được dựng lại từ
     * ảnh gốc bằng `gallery:build`, nên gỡ khỏi album là đủ — xoá tệp ở đó chỉ
     * làm lệnh dựng lần sau mất dữ liệu.
     */
    private function deleteUploadedFiles(GalleryItem $item): void
    {
        foreach ($item->filePaths() as $path) {
            if (! str_starts_with($path, 'storage/')) {
                continue;
            }

            Storage::disk('public')->delete(Str::after($path, 'storage/'));
        }
    }
}
