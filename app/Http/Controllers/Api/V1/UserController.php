<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();

        $users = User::query();

        if (! $actor->isSuperAdmin()) {
            $users->where('shop_id', $actor->shop_id);

            if ($actor->role === UserRole::Owner) {
                $users->where(function ($query) use ($actor): void {
                    $query
                        ->where('id', $actor->id)
                        ->orWhere('role', UserRole::Seller->value);
                });
            } elseif ($actor->role === UserRole::Seller) {
                $users->whereKey($actor->id);
            }
        } elseif ($request->filled('shop_id')) {
            $users->where('shop_id', $request->integer('shop_id'));
        }

        return UserResource::collection(
            $users->latest('id')->paginate($request->integer('limit', 20))->withQueryString()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): UserResource
    {
        $this->authorize('create', User::class);

        $actor = $request->user();
        $data = $request->validated();

        if ($actor->role === UserRole::Owner) {
            if (($data['role'] ?? null) !== UserRole::Seller->value) {
                abort(403);
            }

            $data['shop_id'] = $actor->shop_id;
        } elseif (! $actor->isSuperAdmin()) {
            if (($data['role'] ?? null) === UserRole::SuperAdmin->value) {
                abort(403);
            }

            $data['shop_id'] = $actor->shop_id;
        } elseif (($data['role'] ?? null) !== UserRole::SuperAdmin->value && empty($data['shop_id'])) {
            throw ValidationException::withMessages([
                'shop_id' => ['shop_id is required for non-super-admin users.'],
            ]);
        }

        if (($data['role'] ?? null) === UserRole::SuperAdmin->value) {
            $data['shop_id'] = null;
        }

        $user = User::query()->create([
            ...$data,
            'password' => Hash::make($data['password']),
        ]);

        return new UserResource($user);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $actor = $request->user();
        $data = $request->validated();

        if ($actor->role === UserRole::Seller) {
            abort_if($actor->id !== $user->id, 403, 'Sellers can only update their own profile.');
            unset($data['role'], $data['shop_id']);
        } elseif ($actor->role === UserRole::Owner) {
            if ($actor->id === $user->id) {
                unset($data['role'], $data['shop_id']);
            } else {
                if (($data['role'] ?? UserRole::Seller->value) !== UserRole::Seller->value) {
                    abort(403);
                }

                $data['role'] = UserRole::Seller->value;
                $data['shop_id'] = $actor->shop_id;
            }
        }

        if (! $actor->isSuperAdmin()) {
            if (($data['role'] ?? null) === UserRole::SuperAdmin->value) {
                abort(403);
            }

            $data['shop_id'] = $actor->shop_id;
        }

        if (array_key_exists('password', $data)) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->fill($data);
        $user->save();

        return new UserResource($user);
    }

    /**
     * Mark a user as requiring PIN reset on their next login.
     *
     * The next time the user authenticates, the mobile client clears the local
     * PIN hash, the server clears the flag, and the user is forced through PIN
     * setup again. Invalidating Sanctum tokens guarantees the device cannot
     * keep using a session that bypassed the new PIN.
     */
    public function resetPin(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        abort_unless($actor !== null && $actor->isSuperAdmin(), 403);
        abort_if($actor->id === $user->id, 422, 'Use the standard PIN flow for your own account.');

        $user->forceFill(['pin_reset_required' => true])->save();
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'PIN reset queued. The user will be asked to set a new PIN on next login.',
            'data' => new UserResource($user->refresh()),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        abort_if($request->user()->id === $user->id, 422, 'You cannot delete your own account.');

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted.',
            'data' => null,
        ]);
    }
}
