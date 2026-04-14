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

        $user->loadMissing('shop');

        if (! $user->isSuperAdmin() && $user->shop?->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Shop is suspended.',
                'errors' => [],
            ], 403);
        }

        // Enforce device limits: maximum 3 active sessions
        if ($user->tokens()->count() >= 3) {
            $user->tokens()->orderBy('created_at', 'desc')->skip(2)->get()->each->delete();
        }

        $newToken = $user->createToken($request->input('device_name', 'mobile-app'));
        $token = $newToken->plainTextToken;

        $this->auditLogger->log('auth.login', $user, metadata: [
            'device_name' => $request->input('device_name', 'mobile-app'),
            'token_id' => $newToken->accessToken->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Authenticated.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $user->only(['id', 'shop_id', 'name', 'email', 'role']),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $request->user()->only(['id', 'shop_id', 'name', 'email', 'role']),
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
            $user->tokens()->orderBy('created_at', 'desc')->skip(2)->get()->each->delete();
        }

        $newToken = $user->createToken($request->input('device_name', 'mobile-app'));
        $token = $newToken->plainTextToken;

        $this->auditLogger->log('auth.refresh', $user, metadata: [
            'previous_token_id' => $previousTokenId,
            'token_id' => $newToken->accessToken->id,
            'device_name' => $request->input('device_name', 'mobile-app'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $user->only(['id', 'shop_id', 'name', 'email', 'role']),
            ],
        ]);
    }
}
