<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Optimistic locking helper for write endpoints.
 *
 * Pattern: every entity that supports concurrent edits carries an integer
 * `version` column, incremented on every write. Clients send their last-known
 * `version` on update/delete. If the server's version differs, the request is
 * rejected with 409 + a `server_data` payload so the client can re-render with
 * the authoritative state and ask the user how to resolve.
 *
 * Why a trait, not a base controller method:
 *   - Controllers extend our application Controller; threading version logic
 *     through it would impose the dependency on every controller.
 *   - Traits compose; controllers that don't need locking simply don't `use`
 *     this concern.
 *
 * Semantics:
 *   - Version checks are only enforced if the request explicitly carries the
 *     key (`$request->has('version')`). Missing version means "client opted
 *     out of the check" — typical for legacy clients pre-rollout. Once the
 *     mobile rollout is universal, callers should swap `has()` to require
 *     the key (or the FormRequest can mark it required).
 *   - `throwResponse()` is used so the 409 bypasses any envelope-wrapping
 *     middleware (WrapApiResponse) — the body is already shaped for the
 *     mobile conflict pipeline (`success`, `message`, `server_data`).
 */
trait EnforcesEntityVersion
{
    /**
     * Throws a 409 response when the client's version doesn't match the
     * server's. Returns silently when the client did not send a version.
     *
     * @param  Request  $request  The current request.
     * @param  Model  $entity  The entity being mutated. Must expose `->version` (int).
     * @param  callable(): JsonResource  $serverDataFactory  Lazy producer of the resource representation included
     *                                                       under `server_data`. Lazy because building the resource
     *                                                       can be expensive (eager-loads, scoping); we only run it
     *                                                       on the conflict path.
     * @param  string  $entityName  Human-readable noun for the error message. Lower-case.
     */
    protected function enforceVersionMatch(
        Request $request,
        Model $entity,
        callable $serverDataFactory,
        string $entityName,
    ): void {
        if (! $request->has('version')) {
            return;
        }

        $clientVersion = $request->integer('version');
        if ($entity->version === $clientVersion) {
            return;
        }

        response()->json([
            'success' => false,
            'message' => "Conflict: {$entityName} was modified by another client.",
            'server_data' => $serverDataFactory(),
        ], 409)->throwResponse();
    }
}
