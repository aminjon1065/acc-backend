<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePurchaseRequest;
use App\Http\Resources\Api\V1\PurchaseResource;
use App\Models\Purchase;
use App\Repositories\Api\V1\PurchaseRepository;
use App\Services\Api\V1\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseRepository $purchases,
        private readonly PurchaseService $purchaseService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Purchase::class);

        $purchases = $this->purchases->paginateForUser(
            $request->user(),
            $request->integer('limit', 20),
            $request,
            ['items.product'],
        );

        return PurchaseResource::collection($purchases);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePurchaseRequest $request): PurchaseResource
    {
        $this->authorize('create', Purchase::class);

        $actor = $request->user();
        $shopId = $actor->resolveShopIdForWrite($request->integer('shop_id') ?: null);

        $purchase = $this->purchaseService->createPurchase(
            $actor,
            $shopId,
            $request->input('supplier_name'),
            $request->validated('items'),
        );

        return new PurchaseResource($purchase);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Purchase $purchase): PurchaseResource
    {
        $this->authorize('view', $purchase);

        $scoped = $this->purchases->findForUser($request->user(), $purchase->id, ['items.product']);

        return new PurchaseResource($scoped);
    }
}
