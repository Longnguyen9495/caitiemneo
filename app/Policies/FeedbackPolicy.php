<?php

namespace App\Policies;

use App\Models\Feedback;
use App\Models\User;

/**
 * Feedback đã duyệt là chữ nằm trên mặt tiền của tiệm.
 *
 * Nhân viên đọc được hàng đợi để biết khách đang nói gì về mình, nhưng quyết
 * định cho lên trang hay gỡ xuống thuộc về chủ và quản lý.
 */
class FeedbackPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function moderate(User $user, Feedback $feedback): bool
    {
        return $user->isOwner() || $user->isManager();
    }

    public function delete(User $user, Feedback $feedback): bool
    {
        return $this->moderate($user, $feedback);
    }
}
