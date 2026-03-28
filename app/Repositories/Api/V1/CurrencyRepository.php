<?php

namespace App\Repositories\Api\V1;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class CurrencyRepository
{
    /**
     * @return Builder<Currency>
     */
    public function query(): Builder
    {
        return Currency::query();
    }

    public function paginate(int $limit): LengthAwarePaginator
    {
        return $this->query()
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->paginate($limit)
            ->withQueryString();
    }

    public function unsetDefaultExcept(int $exceptId): void
    {
        $this->query()->whereKeyNot($exceptId)->update(['is_default' => false]);
    }
}
