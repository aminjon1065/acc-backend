<?php

namespace App\Concerns;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Automatically scopes queries to the authenticated user's shop.
 * Super admins bypass this scope and can see all records.
 *
 * Apply this trait to any model that has a `shop_id` column.
 */
trait BelongsToShop
{
    /**
     * Boot the trait and register the global scope.
     */
    protected static function bootBelongsToShop(): void
    {
        static::addGlobalScope('shop', function (Builder $builder) {
            $user = Auth::user();

            if ($user === null) {
                return;
            }

            // Super admins can access all shops
            if ($user->isSuperAdmin()) {
                return;
            }

            $builder->where($builder->getModel()->getTable() . '.shop_id', $user->shop_id);
        });
    }

    /**
     * Convenience relationship back to the owning shop.
     * Models that already define shop() will override this automatically
     * because PHP resolves the method on the model first.
     */
    public function shopScoped()
    {
        return $this->belongsTo(Shop::class);
    }
}
