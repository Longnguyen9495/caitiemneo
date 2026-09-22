<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackStatus;
use App\Enums\GalleryAlbum;
use App\Enums\GalleryMediaType;
use App\Models\Feedback;
use App\Models\GalleryItem;
use App\Support\PublicCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(private PublicCatalog $catalog) {}

    public function __invoke(): View
    {
        $services = $this->catalog->offeredServices();

        return view('home', [
            'branches' => $this->catalog->activeBranches(),
            'services' => $services,
            'serviceGroups' => $this->catalog->serviceGroups($services),
            'gallery' => $this->media(GalleryAlbum::Showcase, GalleryMediaType::Photo, 40),
            'videos' => $this->media(GalleryAlbum::Showcase, GalleryMediaType::Video, 8),
            'photoCount' => GalleryItem::query()
                ->inAlbum(GalleryAlbum::Showcase)
                ->ofType(GalleryMediaType::Photo)
                ->count(),
            // Ảnh chụp tin nhắn khách khen và video khách review, do tiệm tải
            // lên. Ảnh và video nằm chung một dải theo thứ tự đăng, vì với khu
            // này thứ mới nhất mới là thứ đáng tin nhất.
            'feedbackPhotos' => $this->media(GalleryAlbum::Feedback, GalleryMediaType::Photo, 12),
            'feedbackVideos' => $this->media(GalleryAlbum::Feedback, GalleryMediaType::Video, 6),
            // Chỉ feedback chữ đã duyệt, và chỉ một dải ngắn: khu này để khách
            // tin tiệm, không phải để đọc hết mọi lời khen từ ngày mở cửa.
            'feedback' => Feedback::query()->published()->limit(9)->get(),
            'feedbackSummary' => $this->feedbackSummary(),
        ]);
    }

    /**
     * Số lượt và điểm trung bình của feedback đang hiện trên trang.
     *
     * Một truy vấn cho cả hai con số, vì chúng luôn xuất hiện cùng nhau và
     * luôn phải nói về cùng một tập dữ liệu.
     *
     * @return array{total: int, average: float|null}
     */
    private function feedbackSummary(): array
    {
        $row = Feedback::query()
            ->where('status', FeedbackStatus::Published)
            ->selectRaw('count(*) as total, avg(rating) as average')
            ->first();

        return [
            'total' => (int) $row->total,
            'average' => $row->average === null ? null : round((float) $row->average, 1),
        ];
    }

    /**
     * Tệp của một khu, theo thứ tự mới đăng đứng trước.
     *
     * Chặn số lượng ngay ở truy vấn: album cứ dài thêm theo năm tháng, mà trang
     * giới thiệu chỉ trưng một dải ngắn — xem hết thì có trang album riêng.
     *
     * @return Collection<int, GalleryItem>
     */
    private function media(GalleryAlbum $album, GalleryMediaType $type, int $limit): Collection
    {
        return GalleryItem::query()
            ->inAlbum($album)
            ->ofType($type)
            ->newestFirst()
            ->limit($limit)
            ->get();
    }
}
