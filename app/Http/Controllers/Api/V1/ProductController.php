<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
use App\Repositories\Api\V1\ProductRepository;
use App\Services\Api\V1\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductService $productService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->products->paginateForUser($request->user(), $request->integer('limit', 20), $request);

        return ProductResource::collection($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): ProductResource
    {
        $this->authorize('create', Product::class);

        $product = $this->productService->createProduct($request->user(), $request->validated());

        return new ProductResource($product);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Product $product): ProductResource
    {
        $this->authorize('view', $product);

        $scoped = $this->products->findForUser($request->user(), $product->id);

        return new ProductResource($scoped);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        $scoped = $this->products->findForUser($request->user(), $product->id);
        $updated = $this->productService->updateProduct($scoped, $request->validated());

        return new ProductResource($updated);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $scoped = $this->products->findForUser($request->user(), $product->id);
        $this->productService->deleteProduct($scoped);

        return response()->json([
            'success' => true,
            'message' => 'Product deleted.',
            'data' => null,
        ]);
    }

    public function movements(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $scoped = $this->products->findForUser($request->user(), $product->id);

        $purchaseMovements = PurchaseItem::query()
            ->with(['purchase.user'])
            ->where('product_id', $scoped->id)
            ->get()
            ->map(function (PurchaseItem $item): array {
                return [
                    'type' => 'purchase',
                    'quantity' => (float) $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                    'created_at' => $item->created_at?->toISOString(),
                    'reference_id' => $item->purchase_id,
                    'reference_type' => 'purchase',
                    'actor_name' => $item->purchase?->user?->name,
                ];
            });

        $saleMovements = SaleItem::query()
            ->with(['sale.user'])
            ->where('product_id', $scoped->id)
            ->get()
            ->map(function (SaleItem $item): array {
                return [
                    'type' => 'sale',
                    'quantity' => (float) $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                    'created_at' => $item->created_at?->toISOString(),
                    'reference_id' => $item->sale_id,
                    'reference_type' => 'sale',
                    'actor_name' => $item->sale?->user?->name,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => [
                'product_id' => $scoped->id,
                'current_stock' => (float) $scoped->stock_quantity,
                'movements' => $purchaseMovements
                    ->concat($saleMovements)
                    ->sortByDesc('created_at')
                    ->values()
                    ->all(),
            ],
        ]);
    }
}
