<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        // Suspended-shop check at login time. Four states:
        //   • super_admin           — always allowed (no shop scope).
        //   • owner with 0 shops    — allowed; the mobile client renders
        //                             NoShopsAssignedScreen until an admin
        //                             assigns a shop. NOT a suspension.
        //   • owner with shops      — at least one must be active.
        //   • seller / single-shop  — their assigned shop must be active.
        if (! $user->isSuperAdmin()) {
            if ($user->role === \App\UserRole::Owner) {
                $totalShops = $user->ownedShops()->count();
                $shopActive = $totalShops === 0
                    ? true
                    : $user->ownedShops()->where('status', 'active')->exists();
            } else {
                $user->loadMissing('shop');
                $shopActive = $user->shop?->status === 'active';
            }

            if (! $shopActive) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shop is suspended.',
                    'errors' => [],
                ], 403);
            }
        }

        // Enforce device limits: maximum 3 active sessions
        if ($user->tokens()->count() >= 3) {
            $user->tokens()->orderBy('created_at', 'desc')->skip(2)->take(1000)->get()->each->delete();
        }

        $newToken = $user->createToken($request->input('device_name', 'mobile-app'));
        $token = $newToken->plainTextToken;

        $this->auditLogger->log('auth.login', $user, metadata: [
            'device_name' => $request->input('device_name', 'mobile-app'),
            'token_id' => $newToken->accessToken->id,
        ]);

        $pinResetRequired = (bool) $user->pin_reset_required;

        // Consume the flag once the user is authenticating again — the mobile
        // client will clear the local PIN and force a fresh setup.
        if ($pinResetRequired) {
            $user->forceFill(['pin_reset_required' => false])->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Authenticated.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => array_merge(
                    $user->only(['id', 'shop_id', 'name', 'email', 'role']),
                    [
                        'shop_name' => $user->shop?->name,
                        'owned_shop_ids' => $user->owned_shop_ids,
                        'pin_reset_required' => $pinResetRequired,
                    ]
                ),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('shop');

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => array_merge(
                $user->only(['id', 'shop_id', 'name', 'email', 'role']),
                [
                    'shop_name' => $user->shop?->name,
                    'owned_shop_ids' => $user->owned_shop_ids,
                    'pin_reset_required' => (bool) $user->pin_reset_required,
                ]
            ),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        $this->auditLogger->log('auth.logout', $user, metadata: [
            'token_id' => $token?->id,
            'device_name' => $token?->name,
        ]);

        $token?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out.',
            'data' => null,
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $request->user()->currentAccessToken();
        $previousTokenId = $currentToken?->id;

        $currentToken?->delete();

        // Enforce device limits: maximum 3 active sessions
        if ($user->tokens()->count() >= 3) {
            $user->tokens()->orderBy('created_at', 'desc')->skip(2)->take(1000)->get()->each->delete();
        }

        $newToken = $user->createToken($request->input('device_name', 'mobile-app'));
        $token = $newToken->plainTextToken;

        $this->auditLogger->log('auth.refresh', $user, metadata: [
            'previous_token_id' => $previousTokenId,
            'token_id' => $newToken->accessToken->id,
            'device_name' => $request->input('device_name', 'mobile-app'),
        ]);

        $user->loadMissing('shop');

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => array_merge(
                    $user->only(['id', 'shop_id', 'name', 'email', 'role']),
                    ['shop_name' => $user->shop?->name]
                ),
            ],
        ]);
    }
}
