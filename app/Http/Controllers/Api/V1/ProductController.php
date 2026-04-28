<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
use App\Models\SaleReturnItem;
use App\Repositories\Api\V1\ProductRepository;
use App\Services\Api\V1\ProductCatalogCache;
use App\Services\Api\V1\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductService $productService,
        private readonly ProductCatalogCache $productCatalogCache,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        // Sync requests (updated_since present) bypass the catalogue cache — they change
        // frequently and the cache key does not account for the filter parameter.
        if ($request->filled('updated_since')) {
            $products = $this->products->paginateForUser(
                $request->user(),
                $request->integer('limit', 100),
                $request,
            );

            return ProductResource::collection($products);
        }

        $scopeShopId = $request->user()->isSuperAdmin()
            ? ($request->filled('shop_id') ? $request->integer('shop_id') : null)
            : (int) $request->user()->shop_id;
        $version = $this->productCatalogCache->versionForShop($scopeShopId);
        $cacheKey = sprintf(
            'products:index:scope_%s:v%d:%s',
            $scopeShopId ?? 'all',
            $version,
            md5(json_encode([
                'page' => $request->integer('page', 1),
                'limit' => $request->integer('limit', 20),
                'search' => trim((string) $request->input('search', '')),
                'stock_status' => $request->string('stock_status')->toString(),
            ], JSON_THROW_ON_ERROR))
        );

        $products = Cache::remember($cacheKey, 300, fn () => $this->products->paginateForUser(
            $request->user(),
            $request->integer('limit', 20),
            $request,
        ));

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

        $scopeShopId = $request->user()->isSuperAdmin() ? $product->shop_id : (int) $request->user()->shop_id;
        $version = $this->productCatalogCache->versionForShop($scopeShopId);
        $cacheKey = "products:show:shop_{$scopeShopId}:product_{$product->id}:v{$version}";

        $scoped = Cache::remember($cacheKey, 300, fn () => $this->products->findForUser($request->user(), $product->id));

        return new ProductResource($scoped);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        $clientVersion = $request->integer('version');
        if ($clientVersion && $product->version !== $clientVersion) {
            return response()->json([
                'success' => false,
                'message' => 'Conflict: product was modified by another client.',
                'server_data' => new ProductResource($this->products->findForUser($request->user(), $product->id)),
            ], 409)->throwResponse();
        }

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

        $isSeller = $request->user()->role === \App\UserRole::Seller;

        $limit = min($request->integer('limit', 50), 200);
        $cursor = $request->input('cursor'); // ISO timestamp for cursor-based pagination
        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');

        // Cursor is the created_at timestamp of the last item from previous page.
        // Items strictly before cursor are excluded (strict inequality for stable pagination).
        $purchaseMovements = $isSeller ? collect() : PurchaseItem::query()
            ->with(['purchase.user'])
            ->where('product_id', $scoped->id)
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($cursor, fn ($q) => $q->where('created_at', '<', $cursor))
            ->latest()
            ->limit($limit)
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
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($cursor, fn ($q) => $q->where('created_at', '<', $cursor))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (SaleItem $item) use ($isSeller): array {
                return [
                    'type' => 'sale',
                    'quantity' => (float) $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                    'created_at' => $item->created_at?->toISOString(),
                    'reference_id' => $item->sale_id,
                    'reference_type' => 'sale',
                    'actor_name' => $isSeller ? null : ($item->sale?->user?->name),
                ];
            });

        $returnMovements = SaleReturnItem::query()
            ->with(['saleReturn.user'])
            ->where('product_id', $scoped->id)
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($cursor, fn ($q) => $q->where('created_at', '<', $cursor))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (SaleReturnItem $item) use ($isSeller): array {
                return [
                    'type' => 'return',
                    'quantity' => (float) $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                    'created_at' => $item->created_at?->toISOString(),
                    'reference_id' => $item->sale_return_id,
                    'reference_type' => 'return',
                    'actor_name' => $isSeller ? null : ($item->saleReturn?->user?->name),
                ];
            });

        $movements = $purchaseMovements
            ->concat($saleMovements)
            ->concat($returnMovements)
            ->sortByDesc('created_at')
            ->values()
            ->all();

        // Build next cursor from last item's created_at
        $nextCursor = null;
        if (count($movements) === $limit) {
            $lastCreatedAt = end($movements)['created_at'] ?? null;
            if ($lastCreatedAt) {
                $nextCursor = $lastCreatedAt;
            }
        }

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => [
                'product_id' => $scoped->id,
                'current_stock' => (float) $scoped->stock_quantity,
                'movements' => $movements,
                'next_cursor' => $nextCursor,
            ],
        ]);
    }
}
