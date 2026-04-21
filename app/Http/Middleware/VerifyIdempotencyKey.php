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

        // Include body hash so the same key with different payload returns 409.
        $bodyHash = md5((string) $request->getContent());
        $cacheKey = "idempotency:{$user->id}:{$shopId}:{$idempotencyKey}:{$bodyHash}";

        if (Cache::has($cacheKey)) {
            $cachedResponse = Cache::get($cacheKey);
            $headers = $cachedResponse['headers'];
            $headers['X-Idempotent-Replayed'] = 'true';

            return response($cachedResponse['content'], $cachedResponse['status'], $headers);
        }

        // Check if the key exists with a DIFFERENT body hash — that is a conflict.
        $conflictKey = "idempotency:{$user->id}:{$shopId}:{$idempotencyKey}";
        if (Cache::has($conflictKey)) {
            return response()->json([
                'message' => 'Idempotency key already used with a different request body.',
                'error' => 'idempotency_conflict',
            ], 409);
        }

        /** @var \Illuminate\Http\Response|\Illuminate\Http\JsonResponse $response */
        $response = $next($request);

        if ($response->isSuccessful()) {
            // Store with the body-specific key for successful replay.
            Cache::put($cacheKey, [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $response->headers->all(),
            ], now()->addHours(24));

            // Also mark the base key as "some payload seen" for conflict detection.
            Cache::put($conflictKey, ['seen' => true], now()->addHours(24));
        }

        return $response;
    }
}
