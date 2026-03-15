<?php

namespace App\Policies;

use App\Models\Debt;
use App\Models\User;

class DebtPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOperationalRole($user);
    }

    public function view(User $user, Debt $debt): bool
    {
        return $user->isSuperAdmin() || $this->inSameShop($user, $debt->shop_id);
    }

    public function create(User $user): bool
    {
        return $this->isOperationalRole($user);
    }

    public function update(User $user, Debt $debt): bool
    {
        return $user->isSuperAdmin() || $this->inSameShop($user, $debt->shop_id);
    }

    public function delete(User $user, Debt $debt): bool
    {
        return false;
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
