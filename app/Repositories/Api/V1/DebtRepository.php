<?php

namespace App\Repositories\Api\V1;

use App\Models\Debt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class DebtRepository
{
    /**
     * @return Builder<Debt>
     */
    public function queryForUser(User $user): Builder
    {
        $query = Debt::query();

        if (! $user->isSuperAdmin()) {
            $query->where('shop_id', $user->shop_id);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Debt
    {
        return Debt::query()->create($attributes);
    }

    /**
     * @param  array<int, string>  $relations
     */
    public function findForUser(User $user, int $id, array $relations = []): Debt
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
    public function paginateForUser(User $user, int $limit, array $relations = [], ?Request $request = null): LengthAwarePaginator
    {
        $query = $this->queryForUser($user)->latest('id');

        if ($request !== null && $request->filled('updated_since')) {
            $query->where('updated_at', '>', $request->input('updated_since'));
        }

        if ($relations !== []) {
            $query->with($relations);
        }

        return $query->paginate($limit)->withQueryString();
    }
}
