<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyIdempotencyKey
{
    private const LOCK_TIMEOUT_SECONDS = 10;

    private const IDEMPOTENCY_TTL_HOURS = 24;

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

        $bodyHash = md5((string) $request->getContent());
        $userId = $user->id;
        $cacheKey = "idempotency:{$userId}:{$shopId}:{$idempotencyKey}:{$bodyHash}";
        $conflictKey = "idempotency:{$userId}:{$shopId}:{$idempotencyKey}";
        $lockKey = "idempotency_lock:{$userId}:{$shopId}:{$idempotencyKey}";

        if (Cache::has($cacheKey)) {
            $cachedResponse = Cache::get($cacheKey);
            $headers = $cachedResponse['headers'];
            $headers['X-Idempotent-Replayed'] = 'true';

            return response($cachedResponse['content'], $cachedResponse['status'], $headers);
        }

        if (Cache::has($conflictKey)) {
            return response()->json([
                'message' => 'Idempotency key already used with a different request body.',
                'error' => 'idempotency_conflict',
            ], 409);
        }

        // Atomic acquire: only one concurrent request wins the lock.
        // Cache::add returns false if the key already exists.
        $lock = Cache::lock($lockKey, self::LOCK_TIMEOUT_SECONDS);

        if (! $lock->get()) {
            // Another request is currently processing this idempotency key.
            return response()->json([
                'message' => 'Request with this idempotency key is currently being processed.',
                'error' => 'idempotency_in_progress',
            ], 409);
        }

        try {
            /** @var \Illuminate\Http\Response|\Illuminate\Http\JsonResponse $response */
            $response = $next($request);

            if ($response->isSuccessful()) {
                Cache::put($cacheKey, [
                    'content' => $response->getContent(),
                    'status' => $response->getStatusCode(),
                    'headers' => $response->headers->all(),
                ], now()->addHours(self::IDEMPOTENCY_TTL_HOURS));

                Cache::put($conflictKey, ['seen' => true], now()->addHours(self::IDEMPOTENCY_TTL_HOURS));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
