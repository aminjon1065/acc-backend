<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Update the authenticated user's profile from the mobile client.
     *
     * Mirrors the response shape of `AuthController::me` so the mobile auth
     * store can drop the response straight into the cached user object after
     * a successful edit.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }

        if (array_key_exists('email', $validated)) {
            if ($user->email !== $validated['email']) {
                $user->email_verified_at = null;
            }
            $user->email = $validated['email'];
        }

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->loadMissing('shop');

        return response()->json([
            'success' => true,
            'message' => 'Profile updated.',
            'data' => array_merge(
                $user->only(['id', 'shop_id', 'name', 'email', 'role']),
                [
                    'shop_name' => $user->shop?->name,
                    'owned_shop_ids' => $user->active_owned_shop_ids,
                    'pin_reset_required' => (bool) $user->pin_reset_required,
                ]
            ),
        ]);
    }
}
