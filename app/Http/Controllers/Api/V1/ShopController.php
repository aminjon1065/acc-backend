<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreShopRequest;
use App\Http\Requests\Api\V1\UpdateShopRequest;
use App\Http\Resources\Api\V1\ShopResource;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShopController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Shop::class);

        $shops = Shop::query();

        if (! $request->user()->isSuperAdmin()) {
            $shops->whereKey($request->user()->shop_id);
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
     * Store a newly created resource in storage.
     */
    public function store(StoreShopRequest $request): ShopResource
    {
        $this->authorize('create', Shop::class);

        $shop = Shop::query()->create([
            ...$request->validated(),
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

        $shop->fill($request->validated());
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
}
