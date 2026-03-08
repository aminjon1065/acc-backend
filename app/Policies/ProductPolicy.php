<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOperationalRole($user);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->isSuperAdmin() || $this->inSameShop($user, $product->shop_id);
    }

    public function create(User $user): bool
    {
        return $this->isOperationalRole($user);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isSuperAdmin() || $this->inSameShop($user, $product->shop_id);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->isSuperAdmin() || $this->inSameShop($user, $product->shop_id);
    }

    public function restore(User $user, Product $product): bool
    {
        return false;
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return false;
    }

    private function isOperationalRole(User $user): bool
    {
        return $user->isSuperAdmin() || $user->shop_id !== null;
    }

    private function inSameShop(User $user, ?int $shopId): bool
    {
        return $shopId !== null && (int) $user->shop_id === $shopId;
    }
}
