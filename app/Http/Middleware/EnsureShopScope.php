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
                abort_if((int) $actor->shop_id !== (int) $parameter->getKey(), 404);

                continue;
            }

            if ($parameter instanceof User) {
                abort_if((int) $parameter->shop_id !== (int) $actor->shop_id, 404);
                abort_if($parameter->isSuperAdmin(), 403);

                continue;
            }

            $shopId = $parameter->getAttribute('shop_id');

            if ($shopId !== null && (int) $shopId !== (int) $actor->shop_id) {
                abort(404);
            }
        }

        return $next($request);
    }
}
