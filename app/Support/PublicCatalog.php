<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as GroupedCollection;

/**
 * Dữ liệu dùng chung của các trang công khai.
 *
 * Trang giới thiệu và trang album đều dựng cùng một biểu mẫu đặt lịch, nên
 * danh sách chi nhánh và bảng giá phải đến từ một chỗ. Chép truy vấn sang
 * trang thứ hai là cách nhanh nhất để hai trang chào hai bảng giá khác nhau.
 */
class PublicCatalog
{
    /** @return Collection<int, Branch> */
    public function activeBranches(): Collection
    {
        return Branch::query()->active()->orderBy('code')->get();
    }

    /**
     * Những dịch vụ ít nhất một cơ sở đang bán, theo thứ tự bảng giá.
     *
     * @return Collection<int, Service>
     */
    public function offeredServices(): Collection
    {
        return Service::query()
            ->active()
            ->whereHas('branchServices', fn ($query) => $query->where('is_active', true))
            ->inMenuOrder()
            ->get();
    }

    /**
     * Bảng giá chia nhóm đúng như menu giấy của tiệm.
     *
     * @param  Collection<int, Service>  $services
     * @return GroupedCollection<string, Collection<int, Service>>
     */
    public function serviceGroups(Collection $services): GroupedCollection
    {
        return $services->groupBy(fn (Service $service): string => $service->category->label());
    }
}
