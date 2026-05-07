<?php

namespace App\Http\Middleware;

use App\Models\Currency;
use App\Models\Shop;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopScope
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        if ($actor === null || $actor->isSuperAdmin()) {
            return $next($request);
        }

        foreach (($request->route()?->parameters() ?? []) as $parameter) {
            if (! $parameter instanceof Model) {
                continue;
            }

            if ($parameter instanceof Currency) {
                continue;
            }

            if ($parameter instanceof Shop) {
                abort_unless($actor->canAccessShop((int) $parameter->getKey()), 404);

                continue;
            }

            if ($parameter instanceof User) {
                abort_if($parameter->isSuperAdmin(), 403);
                $targetShopId = $parameter->shop_id !== null ? (int) $parameter->shop_id : null;
                // Sellers must belong to a shop the actor can access.
                // Owners can edit themselves (User policy handles that, this
                // middleware just guards the URL parameter).
                abort_if(
                    $targetShopId !== null && ! $actor->canAccessShop($targetShopId),
                    404
                );

                continue;
            }

            $shopId = $parameter->getAttribute('shop_id');

            if ($shopId !== null && ! $actor->canAccessShop((int) $shopId)) {
                abort(404);
            }
        }

        return $next($request);
    }
}
