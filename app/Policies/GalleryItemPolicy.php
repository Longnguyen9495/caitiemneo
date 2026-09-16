<?php

namespace App\Policies;

use App\Models\GalleryItem;
use App\Models\User;

/**
 * Album là mặt tiền quảng cáo của tiệm nên chỉ chủ và quản lý được đăng, xoá.
 * Nhân viên vẫn mở xem được để biết tiệm đang trưng những mẫu nào.
 */
class GalleryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isOwner() || $user->isManager();
    }

    public function delete(User $user, GalleryItem $galleryItem): bool
    {
        return $this->create($user);
    }
}
