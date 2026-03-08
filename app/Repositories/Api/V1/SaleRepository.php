<?php

namespace App\Repositories\Api\V1;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class SaleRepository
{
    /**
     * @return Builder<Sale>
     */
    public function queryForUser(User $user): Builder
    {
        $query = Sale::query();

        if (! $user->isSuperAdmin()) {
            $query->where('shop_id', $user->shop_id);
        }

        return $query;
    }

    /**
     * @return Builder<Product>
     */
    public function queryProductsForShop(User $user, int $shopId): Builder
    {
        $query = Product::query()->where('shop_id', $shopId);

        if (! $user->isSuperAdmin()) {
            $query->where('shop_id', $user->shop_id);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Sale
    {
        return Sale::query()->create($attributes);
    }

    /**
     * @param  array<int, string>  $relations
     */
    public function findForUser(User $user, int $id, array $relations = []): Sale
    {
        $query = $this->queryForUser($user);

        if ($relations !== []) {
            $query->with($relations);
        }

        return $query->findOrFail($id);
    }

    /**
     * @param  array<int, string>  $relations
     */
    public function paginateForUser(User $user, int $limit, array $relations = []): LengthAwarePaginator
    {
        $query = $this->queryForUser($user)->latest('id');

        if ($relations !== []) {
            $query->with($relations);
        }

        return $query->paginate($limit)->withQueryString();
    }
}
