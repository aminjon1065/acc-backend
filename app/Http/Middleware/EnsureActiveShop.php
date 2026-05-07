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

        // Multi-shop ownership: owners are blocked only when EVERY shop
        // they own is suspended. Per-shop checks happen at the policy /
        // repository layer so the owner can still operate on whichever
        // shop is active. Sellers (single shop) keep the original check.
        if ($user->role === \App\UserRole::Owner) {
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
