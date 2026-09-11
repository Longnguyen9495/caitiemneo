<?php

namespace App\Policies;

use App\Models\RiskFlag;
use App\Models\User;

/**
 * Hàng đợi cảnh báo nêu tên người bằng hàm ý, nên chỉ leadership được xem.
 */
class RiskFlagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLeadership();
    }

    public function view(User $user, RiskFlag $flag): bool
    {
        return $this->viewAny($user) && $user->canAccessBranch($flag->branch_id);
    }

    /**
     * Kết luận về một cảnh báo.
     *
     * Người bị cảnh báo nêu tên không được tự đóng cảnh báo của chính mình —
     * đó đúng là tình huống mà việc xem xét sinh ra để phát hiện.
     */
    public function review(User $user, RiskFlag $flag): bool
    {
        return $this->view($user, $flag)
            && (int) $flag->actor_id !== (int) $user->getKey();
    }
}
