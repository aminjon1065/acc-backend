<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOperationalRole($user);
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->isSuperAdmin() || $this->inSameShop($user, $sale->shop_id);
    }

    public function create(User $user): bool
    {
        return $this->isOperationalRole($user);
    }

    public function update(User $user, Sale $sale): bool
    {
        return false;
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

    private function isOperationalRole(User $user): bool
    {
        return $user->isSuperAdmin() || $user->shop_id !== null;
    }

    private function inSameShop(User $user, ?int $shopId): bool
    {
        return $shopId !== null && (int) $user->shop_id === $shopId;
    }
}
