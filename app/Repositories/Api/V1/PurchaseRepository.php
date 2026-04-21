<?php

namespace App\Repositories\Api\V1;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PurchaseRepository
{
    /**
     * @return Builder<Purchase>
     */
    public function queryForUser(User $user): Builder
    {
        $query = Purchase::query();

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
    public function create(array $attributes): Purchase
    {
        return Purchase::query()->create($attributes);
    }

    /**
     * @param  array<int, string>  $relations
     */
    public function findForUser(User $user, int $id, array $relations = []): Purchase
    {
        $query = $this->queryForUser($user);

        if ($relations !== []) {
            $query->with($relations);
        }

        return $query->findOrFail($id);
    }

    /**
     * @param  array<int, string>  $relations
     * @param  array<string, mixed>  $relations2
     */
    public function paginateForUser(User $user, int $limit, ?Request $request = null, array $relations = []): LengthAwarePaginator
    {
        $query = $this->queryForUser($user);

        if ($relations !== []) {
            $query->with($relations);
        }

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

        // Include soft-deleted records so mobile clients can delete local copies.
        $query->withTrashed();

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($limit)
            ->withQueryString();
    }
}
