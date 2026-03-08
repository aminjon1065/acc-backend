<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
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

        $products = $this->products->paginateForUser($request->user(), $request->integer('limit', 20));

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
}
