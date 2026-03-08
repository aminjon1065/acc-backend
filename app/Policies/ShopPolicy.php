<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;

class ShopPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->shop_id !== null;
    }

    public function view(User $user, Shop $shop): bool
    {
        return $user->isSuperAdmin() || (int) $user->shop_id === (int) $shop->id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Shop $shop): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Shop $shop): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, Shop $shop): bool
    {
        return false;
    }

    public function forceDelete(User $user, Shop $shop): bool
    {
        return false;
    }
}
