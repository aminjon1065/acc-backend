<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveShop
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isSuperAdmin()) {
            return $next($request);
        }

        // Multi-shop ownership.
        //   • 0 shops  → pass through. The mobile client gates the user
        //                with NoShopsAssignedScreen; this is not a
        //                suspension and shouldn't block API access.
        //   • >=1 shop → at least one must be active. Per-shop checks
        //                happen at the policy / repository layer so the
        //                owner can still operate on whichever shop is
        //                active.
        // Sellers (single shop) keep the original check below.
        if ($user->role === \App\UserRole::Owner) {
            $totalShops = $user->ownedShops()->count();
            if ($totalShops === 0) {
                return $next($request);
            }
            $hasActiveShop = $user->ownedShops()->where('status', 'active')->exists();
            if (! $hasActiveShop) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Shop is suspended.',
                    'errors' => [],
                ], 403);
            }

            return $next($request);
        }

        $user->loadMissing('shop');

        if ($user->shop?->status !== 'active') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Shop is suspended.',
                'errors' => [],
            ], 403);
        }

        return $next($request);
    }
}
