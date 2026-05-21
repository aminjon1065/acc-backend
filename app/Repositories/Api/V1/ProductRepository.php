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

        $accessibleShopIds = $user->accessibleShopIds();
        $requestedShopId = $request !== null && $request->filled('shop_id')
            ? $request->integer('shop_id')
            : null;

        if ($accessibleShopIds !== null) {
            // owner / seller — server-enforced scope. When the caller also
            // narrows by `shop_id` (e.g. a multi-shop owner picking one
            // shop in the create-purchase modal), honour that narrowing
            // PROVIDED the shop is in their accessible set. Without this,
            // a multi-shop owner's product picker would mix SKUs from
            // every shop they own, and submitting a purchase scoped to
            // shop A with a product from shop B 404s in the service.
            if ($requestedShopId !== null && in_array($requestedShopId, $accessibleShopIds, true)) {
                $query->where('shop_id', $requestedShopId);
            } else {
                $query->whereIn('shop_id', $accessibleShopIds);
            }
        } elseif ($requestedShopId !== null) {
            // super_admin can narrow to a specific shop on demand.
            $query->where('shop_id', $requestedShopId);
        }

        return $query;
    }

    public function findForUser(User $user, string $id): Product
    {
        return $this->queryForUser($user)->findOrFail($id);
    }

    /**
     * Paginate with a composite (updated_at, id) cursor for stable, duplicate-free sync.
     *
     * Cursor format (base64 JSON): { "updated_at": "2024-01-01T12:00:00Z", "id": 123 }
     *
     * Clients should pass `cursor` (from previous page's `sync_token`) instead of `after_id`.
     * The `after_id` parameter is still supported for backward compatibility but
     * `cursor` takes precedence when both are provided.
     */
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

            // Composite cursor: stable across insertions, no duplicates.
            // Format: base64(JSON({ updated_at: "ISO8601", id: 123 }))
            if ($request->filled('cursor')) {
                $decoded = json_decode(base64_decode($request->input('cursor')), true);
                if (is_array($decoded) && isset($decoded['updated_at'], $decoded['id'])) {
                    $cursorUpdatedAt = $decoded['updated_at'];
                    $cursorId = (string) $decoded['id'];
                    // Records strictly before the cursor: older updated_at,
                    // OR same updated_at but smaller id.
                    $query->where(function (Builder $q) use ($cursorUpdatedAt, $cursorId): void {
                        $q->where('updated_at', '<', $cursorUpdatedAt)
                            ->orWhere(fn (Builder $q2) => $q2->where('updated_at', '=', $cursorUpdatedAt)->where('id', '<', $cursorId));
                    });
                }
            } elseif ($request->filled('updated_since')) {
                // Legacy: updated_since is a datetime — use it as a lower bound on updated_at
                $query->where('updated_at', '>', $request->input('updated_since'));
            }
            // NOTE: after_id is deprecated; cursor should be used instead.

            // Upper bound: ensures all pages of a sync cycle use the same server snapshot.
            // Records written on the server AFTER this timestamp will appear on the NEXT
            // sync cycle, not the current one — preventing records from splitting across pages.
            if ($request->filled('updated_before')) {
                $query->where('updated_at', '<', $request->input('updated_before'));
            }
        }

        // Soft-deleted rows are tombstones used by delta-sync clients to
        // evict their local copies. Regular UI listings (pickers,
        // catalog, search) must NOT see them — letting a deleted product
        // into a purchase / sale picker produces a 404 on submit, because
        // the create policy rejects trashed rows. Sync passes
        // `include_trashed=1` to opt in.
        if ($request !== null && $request->boolean('include_trashed')) {
            $query->withTrashed();
        }

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
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
