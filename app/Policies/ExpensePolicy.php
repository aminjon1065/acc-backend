<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\UserRole;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        // Sellers can list expenses too — their list is filtered to
        // rows they personally created at the repository level.
        return $this->isOperationalRole($user);
    }

    public function view(User $user, Expense $expense): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if (! $this->inSameShop($user, $expense->shop_id)) {
            return false;
        }
        if ($user->role === UserRole::Seller) {
            return $expense->user_id === $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        // Operational roles (owner + seller) can create.
        return $this->isOperationalRole($user);
    }

    public function update(User $user, Expense $expense): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if (! $this->inSameShop($user, $expense->shop_id)) {
            return false;
        }
        // Sellers may correct typos on their own rows; cross-seller
        // edits are blocked.
        if ($user->role === UserRole::Seller) {
            return $expense->user_id === $user->id;
        }

        return true;
    }

    public function delete(User $user, Expense $expense): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if (! $this->inSameShop($user, $expense->shop_id)) {
            return false;
        }
        // Sellers may delete their own rows; cross-seller deletion stays
        // blocked. Owners can delete any expense in their accessible shops.
        if ($user->role === UserRole::Seller) {
            return $expense->user_id === $user->id;
        }

        return true;
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
