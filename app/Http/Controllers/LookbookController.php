<?php

namespace App\Http\Controllers;

use App\Enums\GalleryAlbum;
use App\Enums\GalleryMediaType;
use App\Models\GalleryItem;
use App\Support\PublicCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trang album: xem hết mẫu móng của tiệm và đặt lịch ngay từ tấm ảnh.
 *
 * Trang giới thiệu chỉ trưng một dải ngắn cho đẹp; ở đây khách xem kỹ từng
 * mẫu, nên album được chia trang thay vì đổ hết một lần — album chỉ dài thêm
 * theo thời gian, còn dữ liệu di động của khách thì không.
 */
class LookbookController extends Controller
{
    private const PHOTOS_PER_PAGE = 36;

    public function __construct(private PublicCatalog $catalog) {}

    public function __invoke(Request $request): View
    {
        return view('lookbook', [
            'photos' => GalleryItem::query()
                ->inAlbum(GalleryAlbum::Showcase)
                ->ofType(GalleryMediaType::Photo)
                ->newestFirst()
                ->paginate(self::PHOTOS_PER_PAGE)
                ->withQueryString(),
            'branches' => $this->catalog->activeBranches(),
            'serviceGroups' => $this->catalog->serviceGroups($this->catalog->offeredServices()),
            'selectedPhoto' => $this->selectedPhoto($request),
        ]);
    }

    /**
     * Mẫu khách đang chọn khi biểu mẫu bị trả về vì thiếu thông tin.
     *
     * Ảnh có thể nằm ở trang khác của album, nên không tìm lại được trong lưới
     * đang hiển thị; nạp thẳng từ khóa cũ để hộp đặt lịch mở lại đúng tấm ảnh
     * khách đã chọn thay vì bắt họ đi tìm lần nữa.
     */
    private function selectedPhoto(Request $request): ?GalleryItem
    {
        $id = (int) $request->old('gallery_item_id');

        if ($id === 0) {
            return null;
        }

        return GalleryItem::query()
            ->inAlbum(GalleryAlbum::Showcase)
            ->ofType(GalleryMediaType::Photo)
            ->find($id);
    }
}
