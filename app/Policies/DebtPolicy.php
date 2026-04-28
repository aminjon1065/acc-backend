<?php

namespace App\Policies;

use App\Models\Debt;
use App\Models\User;
use App\UserRole;

class DebtPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOperationalRole($user);
    }

    public function view(User $user, Debt $debt): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $this->inSameShop($user, $debt->shop_id)) {
            return false;
        }

        if ($user->role === UserRole::Seller) {
            return $debt->user_id === $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $this->isOperationalRole($user);
    }

    public function update(User $user, Debt $debt): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $this->inSameShop($user, $debt->shop_id)) {
            return false;
        }

        if ($user->role === UserRole::Seller) {
            return $debt->user_id === $user->id;
        }

        return true;
    }

    public function delete(User $user, Debt $debt): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->role === UserRole::Seller) {
            return false; // Sellers cannot delete financial records
        }

        return $this->inSameShop($user, $debt->shop_id);
    }

    public function restore(User $user, Debt $debt): bool
    {
        return false;
    }

    public function forceDelete(User $user, Debt $debt): bool
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
