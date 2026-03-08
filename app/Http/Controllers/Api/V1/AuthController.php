<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }
        $token = $user->createToken($request->input('device_name', 'mobile-app'))->plainTextToken;

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
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out.',
            'data' => null,
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->user()->currentAccessToken()?->delete();
        $token = $user->createToken($request->input('device_name', 'mobile-app'))->plainTextToken;

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
