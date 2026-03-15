<?php

namespace App\Policies;

use App\Models\User;
use App\UserRole;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Seller;
    }

    public function view(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->role === UserRole::Seller) {
            return $user->id === $model->id;
        }

        if ($user->role === UserRole::Owner) {
            if ($user->id === $model->id) {
                return true;
            }

            return $model->role === UserRole::Seller
                && (int) $user->shop_id === (int) $model->shop_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->role !== UserRole::Seller;
    }

    public function update(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->role === UserRole::Seller) {
            return $user->id === $model->id;
        }

        if ($user->role === UserRole::Owner) {
            if ($user->id === $model->id) {
                return true;
            }

            return $model->role === UserRole::Seller
                && (int) $user->shop_id === (int) $model->shop_id;
        }

        return false;
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->role === UserRole::Seller) {
            return false;
        }

        return $user->role === UserRole::Owner
            && $model->role === UserRole::Seller
            && (int) $user->shop_id === (int) $model->shop_id;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
