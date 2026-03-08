<?php

namespace App\Http\Middleware;

use App\ApiPermissionMatrix;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAbility
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $resource, string $action): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 401);
        abort_unless(ApiPermissionMatrix::allows($user, $resource, $action), 403);

        return $next($request);
    }
}
