<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSaleRequest;
use App\Http\Resources\Api\V1\SaleResource;
use App\Models\Sale;
use App\Repositories\Api\V1\SaleRepository;
use App\Services\Api\V1\SaleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleRepository $sales,
        private readonly SaleService $saleService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sale::class);

        $sales = $this->sales->paginateForUser(
            $request->user(),
            $request->integer('limit', 20),
            ['items.product'],
        );

        return SaleResource::collection($sales);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSaleRequest $request): SaleResource
    {
        $this->authorize('create', Sale::class);

        $actor = $request->user();
        $shopId = $actor->isSuperAdmin()
            ? $request->integer('shop_id')
            : $actor->shop_id;

        if ($actor->isSuperAdmin() && ! $shopId) {
            throw ValidationException::withMessages([
                'shop_id' => ['shop_id is required for super admin sale creation.'],
            ]);
        }

        $sale = $this->saleService->createSale(
            $actor,
            (int) $shopId,
            $request->input('customer_name'),
            $request->input('type', 'product'),
            (float) $request->input('discount', 0),
            (float) $request->input('paid', 0),
            $request->input('payment_type', 'cash'),
            $request->input('notes'),
            $request->validated('items'),
        );

        return new SaleResource($sale);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Sale $sale): SaleResource
    {
        $this->authorize('view', $sale);

        $scoped = $this->sales->findForUser($request->user(), $sale->id, ['items.product']);

        return new SaleResource($scoped);
    }

    /**
     * Update the specified resource.
     */
    public function update(UpdateSaleRequest $request, Sale $sale): SaleResource
    {
        $this->authorize('update', $sale);

        $scoped = $this->sales->findForUser($request->user(), $sale->id);
        $updated = $this->saleService->updateSale($scoped, $request->user(), $request->validated());

        return new SaleResource($updated);
    }
}
