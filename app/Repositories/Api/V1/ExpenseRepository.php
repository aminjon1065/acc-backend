<?php

namespace App\Repositories\Api\V1;

use App\Models\Expense;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpenseRepository
{
    /**
     * @return Builder<Expense>
     */
    public function queryForUser(User $user): Builder
    {
        $query = Expense::query();

        // Sellers see only the expenses they personally recorded — same
        // pattern as SaleRepository. Owners / super_admin see everything
        // within their accessible shops.
        if ($user->role === UserRole::Seller) {
            $query->where('user_id', $user->id);

            return $query;
        }

        $accessibleShopIds = $user->accessibleShopIds();
        if ($accessibleShopIds !== null) {
            $query->whereIn('shop_id', $accessibleShopIds);
        }

        return $query;
    }

    public function findForUser(User $user, string $id): Expense
    {
        return $this->queryForUser($user)->with('user:id,name')->findOrFail($id);
    }

    public function paginateForUser(User $user, int $limit, ?Request $request = null): LengthAwarePaginator
    {
        // Eager-load `user:id,name` so the resource can render
        // `created_by_name` without an N+1 lookup per row. Owners use
        // this to attribute expenses to specific sellers; the column
        // collapses to null for soft-deleted sellers.
        $query = $this->queryForUser($user)->with('user:id,name');

        if ($request !== null) {
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

        // Include soft-deleted records so mobile clients can delete local copies.
        $query->withTrashed();

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($limit)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Expense
    {
        return Expense::query()->create($attributes);
    }
}
