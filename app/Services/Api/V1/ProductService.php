<?php

namespace App\Services\Api\V1;

use App\Models\Product;
use App\Models\User;
use App\Repositories\Api\V1\ProductRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function __construct(private readonly ProductRepository $products) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createProduct(User $user, array $validated): Product
    {
        $shopId = $user->isSuperAdmin() ? $validated['shop_id'] : $user->shop_id;
        $imagePath = $this->storeImage($validated['image'] ?? null, $shopId);
        unset($validated['image']);

        return $this->products->create([
            ...$validated,
            'shop_id' => $shopId,
            'created_by' => $user->id,
            'unit' => $validated['unit'] ?? 'piece',
            'low_stock_alert' => $validated['low_stock_alert'] ?? 0,
            'image_path' => $imagePath,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateProduct(Product $product, array $validated): Product
    {
        $removeImage = (bool) ($validated['remove_image'] ?? false);

        if (array_key_exists('image', $validated)) {
            $this->deleteImage($product->image_path);
            $validated['image_path'] = $this->storeImage($validated['image'], $product->shop_id);
        } elseif ($removeImage) {
            $this->deleteImage($product->image_path);
            $validated['image_path'] = null;
        }

        unset($validated['image'], $validated['remove_image']);

        $product->fill($validated);
        $product->save();

        return $product;
    }

    public function deleteProduct(Product $product): void
    {
        $this->deleteImage($product->image_path);
        $product->delete();
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
