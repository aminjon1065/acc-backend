<?php

namespace App\Services\Api\V1;

use App\Models\Product;
use App\Models\User;
use App\Repositories\Api\V1\ProductRepository;

class ProductService
{
    public function __construct(private readonly ProductRepository $products) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createProduct(User $user, array $validated): Product
    {
        return $this->products->create([
            ...$validated,
            'shop_id' => $user->shop_id,
            'created_by' => $user->id,
            'unit' => $validated['unit'] ?? 'piece',
            'low_stock_alert' => $validated['low_stock_alert'] ?? 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateProduct(Product $product, array $validated): Product
    {
        $product->fill($validated);
        $product->save();

        return $product;
    }

    public function deleteProduct(Product $product): void
    {
        $product->delete();
    }
}
