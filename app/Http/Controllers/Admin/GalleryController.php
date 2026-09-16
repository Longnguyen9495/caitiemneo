<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Gallery\StoreGalleryMediaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GalleryUploadRequest;
use App\Models\GalleryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GalleryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', GalleryItem::class);

        return view('admin.gallery.index', [
            'items' => GalleryItem::query()->with('uploader')->newestFirst()->paginate(24),
            'maxFiles' => GalleryUploadRequest::MAX_FILES,
            'maxMegabytes' => (int) round(GalleryUploadRequest::MAX_KILOBYTES / 1024),
        ]);
    }

    public function store(GalleryUploadRequest $request, StoreGalleryMediaAction $action): RedirectResponse
    {
        $stored = 0;
        $failed = [];

        foreach ($request->file('media') as $file) {
            if ($action->handle($file, $request->user()) === null) {
                $failed[] = $file->getClientOriginalName();

                continue;
            }

            $stored++;
        }

        $redirect = redirect()->route('admin.gallery.index');

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

        return redirect()->route('admin.gallery.index')->with('success', 'Đã gỡ khỏi album.');
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
