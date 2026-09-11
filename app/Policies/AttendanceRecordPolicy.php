<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isManager();
    }

    public function view(User $user, AttendanceRecord $record): bool
    {
        return $this->viewAny($user) && $user->canAccessBranch($record->branch_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AttendanceRecord $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, AttendanceRecord $record): bool
    {
        return $this->view($user, $record);
    }
}
