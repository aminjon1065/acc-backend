<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;
use App\UserRole;

class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOwnerOrAdmin($user);
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->isSuperAdmin() || ($user->role === UserRole::Owner && $this->inSameShop($user, $purchase->shop_id));
    }

    public function create(User $user): bool
    {
        return $this->isOwnerOrAdmin($user);
    }

    public function update(User $user, Purchase $purchase): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->role === UserRole::Owner && $user->canAccessShop((int) $purchase->shop_id);
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->role === UserRole::Owner && $user->canAccessShop((int) $purchase->shop_id);
    }

    public function restore(User $user, Purchase $purchase): bool
    {
        return false;
    }

    public function forceDelete(User $user, Purchase $purchase): bool
    {
        return false;
    }

    private function isOperationalRole(User $user): bool
    {
        return $user->hasAnyShop();
    }

    private function isOwnerOrAdmin(User $user): bool
    {
        return $user->isSuperAdmin() || $user->role === UserRole::Owner;
    }

    private function inSameShop(User $user, ?int $shopId): bool
    {
        return $shopId !== null && $user->canAccessShop($shopId);
    }
}
