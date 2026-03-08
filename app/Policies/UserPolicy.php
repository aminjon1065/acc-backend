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

        return $model->role !== UserRole::SuperAdmin
            && (int) $user->shop_id === (int) $model->shop_id;
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

        return $model->role !== UserRole::SuperAdmin
            && (int) $user->shop_id === (int) $model->shop_id;
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->role === UserRole::Seller) {
            return false;
        }

        return $model->role !== UserRole::SuperAdmin
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
