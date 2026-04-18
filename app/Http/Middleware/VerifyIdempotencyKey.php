<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyIdempotencyKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return $next($request);
        }

        $user = $request->user();
        $shopId = $user?->isSuperAdmin()
            ? $request->input('shop_id')
            : $user?->shop_id;
        $cacheKey = "idempotency:{$user->id}:{$shopId}:{$idempotencyKey}";

        if (Cache::has($cacheKey)) {
            $cachedResponse = Cache::get($cacheKey);
            $headers = $cachedResponse['headers'];
            $headers['X-Idempotent-Replayed'] = 'true';

            return response($cachedResponse['content'], $cachedResponse['status'], $headers);
        }

        /** @var \Illuminate\Http\Response|\Illuminate\Http\JsonResponse $response */
        $response = $next($request);

        if ($response->isSuccessful()) {
            Cache::put($cacheKey, [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $response->headers->all(),
            ], now()->addHours(24));
        }

        return $response;
    }
}
