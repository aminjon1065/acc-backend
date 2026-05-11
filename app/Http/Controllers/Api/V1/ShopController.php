<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreShopRequest;
use App\Http\Requests\Api\V1\UpdateShopRequest;
use App\Http\Resources\Api\V1\ShopResource;
use App\Models\Shop;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ShopController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Shop::class);

        $shops = Shop::query();

        $accessibleShopIds = $request->user()->accessibleShopIds();
        if ($accessibleShopIds !== null) {
            $shops->whereIn('id', $accessibleShopIds);
        }

        // Delta-sync support: clients pass updated_since to receive only
        // records changed after their last sync, plus soft-deleted rows so
        // local copies can be removed.
        if ($request->filled('updated_since')) {
            $shops->where('updated_at', '>', $request->input('updated_since'));
            $shops->withTrashed();
        }

        if ($request->filled('updated_before')) {
            $shops->where('updated_at', '<', $request->input('updated_before'));
        }

        return ShopResource::collection(
            $shops->orderByDesc('updated_at')->orderByDesc('id')
                ->paginate($request->integer('limit', 20))
                ->withQueryString()
        );
    }

    /**
     * Lightweight id-list for client-side reconcile. See
     * ProductController::ids for the rationale — same contract.
     */
    public function ids(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shop::class);

        $shops = Shop::query();

        $accessibleShopIds = $request->user()->accessibleShopIds();
        if ($accessibleShopIds !== null) {
            $shops->whereIn('id', $accessibleShopIds);
        }

        $rows = $shops
            ->select(['id', 'updated_at'])
            ->orderBy('id')
            ->get()
            ->map(fn (Shop $s) => [
                'id' => $s->id,
                'updated_at' => $s->updated_at?->toISOString(),
            ]);

        return response()->json(['data' => $rows]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreShopRequest $request): ShopResource
    {
        $this->authorize('create', Shop::class);

        $payload = $request->validated();
        $this->assertOwnerIdIsValid($payload['owner_id'] ?? null);

        $shop = Shop::query()->create([
            ...$payload,
            'status' => $request->input('status', 'active'),
        ]);

        return new ShopResource($shop);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Shop $shop): ShopResource
    {
        $this->authorize('view', $shop);

        return new ShopResource($shop);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateShopRequest $request, Shop $shop): ShopResource
    {
        $this->authorize('update', $shop);

        $payload = $request->validated();
        if (array_key_exists('owner_id', $payload)) {
            $this->assertOwnerIdIsValid($payload['owner_id']);
        }

        $shop->fill($payload);
        $shop->save();

        return new ShopResource($shop);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Shop $shop): JsonResponse
    {
        $this->authorize('delete', $shop);

        $shop->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shop deleted.',
            'data' => null,
        ]);
    }

    /**
     * Validate that an owner_id assignment targets a user with the owner
     * role. The FK + `exists:users,id` rule ensures the row exists; this
     * extra step prevents accidentally turning a seller or another super
     * admin into an "owner" via the shops endpoint.
     */
    private function assertOwnerIdIsValid(?int $ownerId): void
    {
        if ($ownerId === null) {
            return;
        }

        $candidate = User::query()->find($ownerId);
        if ($candidate === null || $candidate->role !== UserRole::Owner) {
            throw ValidationException::withMessages([
                'owner_id' => ['Selected user must have the owner role.'],
            ]);
        }
    }
}
