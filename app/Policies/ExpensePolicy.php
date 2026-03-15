<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\UserRole;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOwnerOrAdmin($user);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->isSuperAdmin() || ($user->role === UserRole::Owner && $this->inSameShop($user, $expense->shop_id));
    }

    public function create(User $user): bool
    {
        return $this->isOwnerOrAdmin($user);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->isSuperAdmin() || ($user->role === UserRole::Owner && $this->inSameShop($user, $expense->shop_id));
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->isSuperAdmin() || ($user->role === UserRole::Owner && $this->inSameShop($user, $expense->shop_id));
    }

    public function restore(User $user, Expense $expense): bool
    {
        return false;
    }

    public function forceDelete(User $user, Expense $expense): bool
    {
        return false;
    }

    private function isOperationalRole(User $user): bool
    {
        return $user->isSuperAdmin() || $user->shop_id !== null;
    }

    private function isOwnerOrAdmin(User $user): bool
    {
        return $user->isSuperAdmin() || $user->role === UserRole::Owner;
    }

    private function inSameShop(User $user, ?int $shopId): bool
    {
        return $shopId !== null && (int) $user->shop_id === $shopId;
    }
}
