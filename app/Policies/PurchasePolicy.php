<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOperationalRole($user);
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->isSuperAdmin() || $this->inSameShop($user, $purchase->shop_id);
    }

    public function create(User $user): bool
    {
        return $this->isOperationalRole($user);
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return false;
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return false;
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
        return $user->isSuperAdmin() || $user->shop_id !== null;
    }

    private function inSameShop(User $user, ?int $shopId): bool
    {
        return $shopId !== null && (int) $user->shop_id === $shopId;
    }
}
