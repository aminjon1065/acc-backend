<?php

namespace App\Services\Api\V1;

use App\Models\Product;
use App\Models\User;
use App\Repositories\Api\V1\ProductRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductCatalogCache $productCatalogCache,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createProduct(User $user, array $validated): Product
    {
        $validated = $this->normalizeImageUpload($validated);
        $shopId = $user->isSuperAdmin() ? $validated['shop_id'] : $user->shop_id;
        $imagePath = $this->storeImage($validated['image'] ?? null, $shopId);
        unset($validated['image']);

        $validated = $this->normalizePricingAttributes($validated);

        $product = $this->products->create([
            ...$validated,
            'shop_id' => $shopId,
            'created_by' => $user->id,
            'unit' => $validated['unit'] ?? 'piece',
            'low_stock_alert' => $validated['low_stock_alert'] ?? 0,
            'image_path' => $imagePath,
            'pricing_mode' => $validated['pricing_mode'] ?? 'fixed',
        ]);

        $this->productCatalogCache->bumpShop((int) $shopId);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateProduct(Product $product, array $validated): Product
    {
        $validated = $this->normalizeImageUpload($validated);
        $removeImage = (bool) ($validated['remove_image'] ?? false);

        if (array_key_exists('image', $validated)) {
            $this->deleteImage($product->image_path);
            $validated['image_path'] = $this->storeImage($validated['image'], $product->shop_id);
        } elseif ($removeImage) {
            $this->deleteImage($product->image_path);
            $validated['image_path'] = null;
        }

        unset($validated['image'], $validated['remove_image']);

        $validated = $this->normalizePricingAttributes($validated, $product);

        $product->fill($validated);
        $product->version = ($product->version ?? 1) + 1;
        $product->save();

        $this->productCatalogCache->bumpShop((int) $product->shop_id);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeImageUpload(array $validated): array
    {
        if (! array_key_exists('image', $validated) && array_key_exists('photo', $validated)) {
            $validated['image'] = $validated['photo'];
        }

        unset($validated['photo']);

        return $validated;
    }

    public function deleteProduct(Product $product): void
    {
        $this->deleteImage($product->image_path);
        $shopId = (int) $product->shop_id;
        $product->delete();
        $this->productCatalogCache->bumpShop($shopId);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePricingAttributes(array $validated, ?Product $product = null): array
    {
        $pricingMode = $validated['pricing_mode'] ?? ($product?->pricing_mode?->value ?? 'fixed');
        $markupPercent = array_key_exists('markup_percent', $validated)
            ? $validated['markup_percent']
            : $product?->markup_percent;
        $costPrice = (float) ($validated['cost_price'] ?? $product?->cost_price ?? 0);

        $validated['pricing_mode'] = $pricingMode;

        if ($pricingMode !== 'markup') {
            $validated['markup_percent'] = null;

            return $validated;
        }

        $markupPercent = $markupPercent !== null ? (float) $markupPercent : null;
        $validated['markup_percent'] = $markupPercent;

        if ($markupPercent !== null) {
            $validated['sale_price'] = round($costPrice * (1 + ($markupPercent / 100)), 2);
        }

        return $validated;
    }

    private function storeImage(mixed $image, ?int $shopId): ?string
    {
        if (! $image instanceof UploadedFile || $shopId === null) {
            return null;
        }

        return $image->store("products/{$shopId}", 'public');
    }

    private function deleteImage(?string $imagePath): void
    {
        if ($imagePath === null || $imagePath === '') {
            return;
        }

        Storage::disk('public')->delete($imagePath);
    }
}
