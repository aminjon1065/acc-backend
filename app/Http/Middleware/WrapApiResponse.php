<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WrapApiResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        if (! $request->is('api/*')) {
            return $response;
        }

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return $response;
        }

        $payload = $response->getData(true);

        if (! is_array($payload) || array_key_exists('success', $payload)) {
            return $response;
        }

        if (array_is_list($payload)) {
            $response->setData([
                'success' => true,
                'message' => '',
                'server_time' => now()->toISOString(),
                'data' => $payload,
            ]);

            return $response;
        }

        $response->setData([
            'success' => true,
            'message' => '',
            'server_time' => now()->toISOString(),
            ...$payload,
        ]);

        return $response;
    }
}
