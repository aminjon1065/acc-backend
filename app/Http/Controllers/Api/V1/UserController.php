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

        if ($actor->isSuperAdmin()) {
            if ($request->filled('shop_id')) {
                $users->where('shop_id', $request->integer('shop_id'));
            }
        } elseif ($actor->role === UserRole::Owner) {
            // Owner sees themselves + sellers across every shop they own.
            $ownedIds = $actor->owned_shop_ids;
            $users->where(function ($query) use ($actor, $ownedIds): void {
                $query
                    ->where('id', $actor->id)
                    ->orWhere(function ($q) use ($ownedIds): void {
                        $q->where('role', UserRole::Seller->value)
                            ->whereIn('shop_id', $ownedIds);
                    });
            });
        } elseif ($actor->role === UserRole::Seller) {
            $users->whereKey($actor->id);
        }

        // Delta-sync support: clients pass updated_since to receive only
        // records changed after their last sync, plus soft-deleted rows so
        // local copies can be removed. Mirrors ShopController::index.
        if ($request->filled('updated_since')) {
            $users->where('updated_at', '>', $request->input('updated_since'));
            $users->withTrashed();
        }

        if ($request->filled('updated_before')) {
            $users->where('updated_at', '<', $request->input('updated_before'));
        }

        return UserResource::collection(
            $users->latest('id')->paginate($request->integer('limit', 20))->withQueryString()
        );
    }

    /**
     * Lightweight id-list for client-side reconcile. See
     * ProductController::ids for the rationale — same contract. The same
     * role-based scoping as `index()` applies here so each role only sees
     * the users it's allowed to know about.
     */
    public function ids(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();
        $users = User::query();

        if ($actor->isSuperAdmin()) {
            if ($request->filled('shop_id')) {
                $users->where('shop_id', $request->integer('shop_id'));
            }
        } elseif ($actor->role === UserRole::Owner) {
            $ownedIds = $actor->owned_shop_ids;
            $users->where(function ($query) use ($actor, $ownedIds): void {
                $query
                    ->where('id', $actor->id)
                    ->orWhere(function ($q) use ($ownedIds): void {
                        $q->where('role', UserRole::Seller->value)
                            ->whereIn('shop_id', $ownedIds);
                    });
            });
        } elseif ($actor->role === UserRole::Seller) {
            $users->whereKey($actor->id);
        }

        $rows = $users
            ->select(['id', 'updated_at'])
            ->orderBy('id')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'updated_at' => $u->updated_at?->toISOString(),
            ]);

        return response()->json(['data' => $rows]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): UserResource
    {
        $this->authorize('create', User::class);

        $actor = $request->user();
        $data = $request->validated();
        $role = $data['role'] ?? null;

        if ($actor->role === UserRole::Owner) {
            // Owners can only create sellers, and only inside their own shops.
            // resolveShopIdForWrite handles the "owner with one shop = implicit"
            // and "owner with many shops = required" cases uniformly.
            if ($role !== UserRole::Seller->value) {
                abort(403, 'Owners can only create sellers.');
            }
            $data['shop_id'] = $actor->resolveShopIdForWrite(
                isset($data['shop_id']) ? (int) $data['shop_id'] : null
            );
        } elseif ($actor->isSuperAdmin()) {
            // super_admin: explicit role/shop_id rules
            //   • super_admin role → shop_id forced to null
            //   • owner role       → shop_id null (owners use shops.owner_id)
            //   • seller role      → shop_id required and must exist
            if ($role === UserRole::SuperAdmin->value) {
                $data['shop_id'] = null;
            } elseif ($role === UserRole::Owner->value) {
                $data['shop_id'] = null;
            } elseif ($role === UserRole::Seller->value) {
                if (empty($data['shop_id'])) {
                    throw ValidationException::withMessages([
                        'shop_id' => ['shop_id is required when creating a seller.'],
                    ]);
                }
            }
        } else {
            // Sellers cannot create users (caught by viewAny / create policy
            // already; this is defense in depth).
            abort(403);
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
                // Owner editing self — role/shop_id are not user-editable.
                unset($data['role'], $data['shop_id']);
            } else {
                // Owner editing one of their sellers. Role can't change away
                // from seller. shop_id may change but only between the
                // owner's own shops.
                if (($data['role'] ?? UserRole::Seller->value) !== UserRole::Seller->value) {
                    abort(403);
                }
                $data['role'] = UserRole::Seller->value;
                if (array_key_exists('shop_id', $data)) {
                    $shopId = $data['shop_id'] !== null ? (int) $data['shop_id'] : null;
                    if ($shopId === null || ! $actor->canAccessShop($shopId)) {
                        throw ValidationException::withMessages([
                            'shop_id' => ['shop_id must be one of your shops.'],
                        ]);
                    }
                    $data['shop_id'] = $shopId;
                }
            }
        }
        // super_admin: no extra coercion — request data stands as validated.

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
