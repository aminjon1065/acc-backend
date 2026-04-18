<?php

namespace App\Repositories\Api\V1;

use App\Models\Expense;
use App\Models\User;
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

        if (! $user->isSuperAdmin()) {
            $query->where('shop_id', $user->shop_id);
        }

        return $query;
    }

    public function findForUser(User $user, int $id): Expense
    {
        return $this->queryForUser($user)->findOrFail($id);
    }

    public function paginateForUser(User $user, int $limit, ?Request $request = null): LengthAwarePaginator
    {
        $query = $this->queryForUser($user)->latest('id');

        if ($request !== null && $request->filled('updated_since')) {
            $query->where('updated_at', '>', $request->input('updated_since'));
        }

        return $query->paginate($limit)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Expense
    {
        return Expense::query()->create($attributes);
    }
}
