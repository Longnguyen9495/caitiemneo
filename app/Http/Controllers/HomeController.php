<?php

namespace App\Http\Controllers;

use App\Enums\GalleryMediaType;
use App\Models\Branch;
use App\Models\GalleryItem;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $services = $this->offeredServices();

        return view('home', [
            'branches' => Branch::query()->active()->orderBy('code')->get(),
            'services' => $services,
            // Bảng giá công khai chia nhóm đúng như menu giấy của tiệm.
            'serviceGroups' => $services->groupBy(fn (Service $service): string => $service->category->label()),
            'gallery' => $this->media(GalleryMediaType::Photo, 40),
            'videos' => $this->media(GalleryMediaType::Video, 8),
        ]);
    }

    /**
     * Những dịch vụ ít nhất một cơ sở đang bán, theo thứ tự bảng giá.
     *
     * @return Collection<int, Service>
     */
    private function offeredServices(): Collection
    {
        return Service::query()
            ->active()
            ->whereHas('branchServices', fn ($query) => $query->where('is_active', true))
            ->inMenuOrder()
            ->get();
    }

    /**
     * Album theo thứ tự mới đăng đứng trước.
     *
     * Chặn số lượng ngay ở truy vấn: album cứ dài thêm theo năm tháng, mà một
     * trang quảng cáo không cần trưng hết mọi tấm tiệm từng làm.
     *
     * @return Collection<int, GalleryItem>
     */
    private function media(GalleryMediaType $type, int $limit): Collection
    {
        return GalleryItem::query()
            ->ofType($type)
            ->newestFirst()
            ->limit($limit)
            ->get();
    }
}
