<?php

namespace App\Repositories\Api\V1;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SaleRepository
{
    /**
     * @return Builder<Sale>
     */
    public function queryForUser(User $user): Builder
    {
        $query = Sale::query();

        if ($user->role === UserRole::Seller) {
            $query->where('user_id', $user->id);
        } elseif (! $user->isSuperAdmin()) {
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
     * @param  array<string, mixed>  $attributes
     */
    public function createReturn(array $attributes): SaleReturn
    {
        return SaleReturn::query()->create($attributes);
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
     * Paginate with a composite (updated_at, id) cursor for stable, duplicate-free sync.
     * See ProductRepository::paginateForUser for cursor format documentation.
     */
    public function paginateForUser(User $user, int $limit, array $relations = [], ?Request $request = null): LengthAwarePaginator
    {
        $query = $this->queryForUser($user);

        if ($request !== null) {
            // Composite cursor: stable across insertions, no duplicates.
            if ($request->filled('cursor')) {
                $decoded = json_decode(base64_decode($request->input('cursor')), true);
                if (is_array($decoded) && isset($decoded['updated_at'], $decoded['id'])) {
                    $cursorUpdatedAt = $decoded['updated_at'];
                    $cursorId = (int) $decoded['id'];
                    // For ORDER BY updated_at DESC, id DESC: get older records after the cursor.
                    $query->where(function (Builder $q) use ($cursorUpdatedAt, $cursorId): void {
                        $q->where('updated_at', '<', $cursorUpdatedAt)
                            ->orWhere(fn (Builder $q2) => $q2->where('updated_at', '=', $cursorUpdatedAt)->where('id', '<', $cursorId));
                    });
                }
            } elseif ($request->filled('updated_since')) {
                $query->where('updated_at', '>', $request->input('updated_since'));
            }

            // Upper bound: ensures all pages of a sync cycle use the same server snapshot.
            if ($request->filled('updated_before')) {
                $query->where('updated_at', '<', $request->input('updated_before'));
            }
        }

        if ($relations !== []) {
            $query->with($relations);
        }

        // Include soft-deleted records so mobile clients can delete local copies.
        $query->withTrashed();

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($limit)
            ->withQueryString();
    }
}
