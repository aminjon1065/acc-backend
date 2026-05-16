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
            // Sellers see only their own sales, regardless of which shop the
            // sale was made in. The shop_id filter is implicit via user_id.
            $query->where('user_id', $user->id);

            return $query;
        }

        $accessibleShopIds = $user->accessibleShopIds();
        if ($accessibleShopIds !== null) {
            // owner — scope to all owned shops.
            $query->whereIn('shop_id', $accessibleShopIds);
        }

        return $query;
    }

    /**
     * @return Builder<Product>
     */
    public function queryProductsForShop(User $user, int $shopId): Builder
    {
        $query = Product::query()->where('shop_id', $shopId);

        $accessibleShopIds = $user->accessibleShopIds();
        if ($accessibleShopIds !== null && ! in_array($shopId, $accessibleShopIds, true)) {
            // The user explicitly asked for a shop they cannot access.
            // Return an empty result set instead of leaking another shop's
            // products. The policy layer is the primary enforcement, this
            // is the second line of defence at the repository level.
            $query->whereRaw('1 = 0');
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
    public function findForUser(User $user, string $id, array $relations = []): Sale
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
            $search = trim((string) $request->input('search', ''));
            if ($search !== '') {
                // Sale ids are UUID strings, so a partial-id search rarely
                // helps. Match on customer_name, notes, and the seller's
                // display name instead — the common "find Vasya's sale"
                // case.
                $like = '%'.$search.'%';
                $query->where(function (Builder $q) use ($like): void {
                    $q->where('customer_name', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like));
                });
            }

            $paymentType = trim((string) $request->input('payment_type', ''));
            if ($paymentType !== '') {
                $query->where('payment_type', $paymentType);
            }

            $type = trim((string) $request->input('type', ''));
            if ($type !== '') {
                $query->where('type', $type);
            }

            if ($request->boolean('debt_only')) {
                $query->where('debt', '>', 0);
            }

            // Composite cursor: stable across insertions, no duplicates.
            if ($request->filled('cursor')) {
                $decoded = json_decode(base64_decode($request->input('cursor')), true);
                if (is_array($decoded) && isset($decoded['updated_at'], $decoded['id'])) {
                    $cursorUpdatedAt = $decoded['updated_at'];
                    $cursorId = (string) $decoded['id'];
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
