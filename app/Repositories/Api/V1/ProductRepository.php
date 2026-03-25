<?php

namespace App\Repositories\Api\V1;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository
{
    /**
     * @return Builder<Product>
     */
    public function queryForUser(User $user, ?Request $request = null): Builder
    {
        $query = Product::query();

        if (! $user->isSuperAdmin()) {
            $query->where('shop_id', $user->shop_id);
        } elseif ($request !== null && $request->filled('shop_id')) {
            $query->where('shop_id', $request->integer('shop_id'));
        }

        return $query;
    }

    public function findForUser(User $user, int $id): Product
    {
        return $this->queryForUser($user)->findOrFail($id);
    }

    public function paginateForUser(User $user, int $limit, ?Request $request = null): LengthAwarePaginator
    {
        $query = $this->queryForUser($user, $request);

        if ($request !== null) {
            $search = trim((string) $request->input('search', ''));
            $stockStatus = $request->string('stock_status')->toString();

            if ($search !== '') {
                $query->where(function (Builder $builder) use ($search): void {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            }

            match ($stockStatus) {
                'low_stock' => $query
                    ->where('stock_quantity', '>', 0)
                    ->whereColumn('stock_quantity', '<=', 'low_stock_alert'),
                'out_of_stock' => $query->where('stock_quantity', '<=', 0),
                'in_stock' => $query->where('stock_quantity', '>', 0),
                default => null,
            };
        }

        return $query->latest('id')->paginate($limit)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Product
    {
        return Product::query()->create($attributes);
    }
}
