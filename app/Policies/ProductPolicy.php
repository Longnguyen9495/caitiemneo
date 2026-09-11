<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /** Employees may look at the stock list but never change it. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isOwner() || $user->isManager();
    }

    public function update(User $user, Product $product): bool
    {
        return $this->create($user);
    }
}
