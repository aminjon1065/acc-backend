<?php

namespace App\Services\Api\V1;

use App\Models\Purchase;
use App\Models\User;
use App\Repositories\Api\V1\PurchaseRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        private readonly PurchaseRepository $purchases,
        private readonly AuditLogger $auditLogger,
        private readonly ProductCatalogCache $productCatalogCache,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function createPurchase(User $actor, int $shopId, ?string $supplierName, array $items): Purchase
    {
        return DB::transaction(function () use ($actor, $shopId, $supplierName, $items): Purchase {
            $purchase = $this->purchases->create([
                'shop_id' => $shopId,
                'user_id' => $actor->id,
                'supplier_name' => $supplierName,
                'total_amount' => 0,
            ]);

            $productIds = collect($items)->pluck('product_id')->unique()->values();

            $products = $this->purchases
                ->queryProductsForShop($actor, $shopId)
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $totalAmount = 0.0;

            foreach ($items as $item) {
                $productId = $item['product_id'];
                $product = $products->get($productId);

                if (! $product) {
                    throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(\App\Models\Product::class, $productId);
                }

                $quantity = (float) $item['quantity'];
                $price = (float) $item['price'];
                $lineTotal = $quantity * $price;

                $purchase->items()->create([
                    'shop_id' => $shopId,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $lineTotal,
                ]);

                $product->increment('stock_quantity', $quantity);
                $product->update(['cost_price' => $price]);

                if (isset($item['markup_percent']) && $item['markup_percent'] !== null) {
                    $markupPercent = (float) $item['markup_percent'];
                    $product->update([
                        'pricing_mode' => 'markup',
                        'markup_percent' => $markupPercent,
                        'sale_price' => round($price * (1 + $markupPercent / 100), 2),
                    ]);
                }

                DB::table('products')->where('id', $product->id)->increment('version');

                $totalAmount += $lineTotal;
            }

            $purchase->update(['total_amount' => $totalAmount]);

            $freshPurchase = $purchase->fresh(['items.product']);

            $this->auditLogger->log('purchases.created', $actor, $freshPurchase, [
                'supplier_name' => $supplierName,
                'items_count' => count($items),
                'total_amount' => (float) $freshPurchase->total_amount,
            ], $shopId);

            $this->productCatalogCache->bumpShop($shopId);

            return $freshPurchase;
        });
    }
}
