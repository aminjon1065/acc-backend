<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;
use App\UserRole;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOperationalRole($user);
    }

    public function view(User $user, Sale $sale): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $this->inSameShop($user, $sale->shop_id)) {
            return false;
        }

        // Seller can only view their own sales
        if ($user->role === UserRole::Seller) {
            return $sale->user_id === $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $this->isOperationalRole($user);
    }

    public function update(User $user, Sale $sale): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->role === UserRole::Owner && (int) $user->shop_id === (int) $sale->shop_id;
    }

    public function delete(User $user, Sale $sale): bool
    {
        return false;
    }

    public function restore(User $user, Sale $sale): bool
    {
        return false;
    }

    public function forceDelete(User $user, Sale $sale): bool
    {
        return false;
    }

    public function return(User $user, Sale $sale): bool
    {
        return $user->isSuperAdmin() || ($user->role === UserRole::Owner && $this->inSameShop($user, $sale->shop_id));
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
