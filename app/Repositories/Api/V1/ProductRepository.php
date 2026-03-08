<?php

namespace App\Repositories\Api\V1;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository
{
    /**
     * @return Builder<Product>
     */
    public function queryForUser(User $user): Builder
    {
        $query = Product::query();

        if (! $user->isSuperAdmin()) {
            $query->where('shop_id', $user->shop_id);
        }

        return $query;
    }

    public function findForUser(User $user, int $id): Product
    {
        return $this->queryForUser($user)->findOrFail($id);
    }

    public function paginateForUser(User $user, int $limit): LengthAwarePaginator
    {
        return $this->queryForUser($user)
            ->latest('id')
            ->paginate($limit)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Product
    {
        return Product::query()->create($attributes);
    }
}
