<?php

namespace App\Services\Api\V1;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Repositories\Api\V1\PurchaseRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        private readonly PurchaseRepository $purchases,
        private readonly AuditLogger $auditLogger,
        private readonly ProductCatalogCache $productCatalogCache,
        private readonly DashboardCacheVersion $dashboardCacheVersion,
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
            // Purchases change stock / cost, which the dashboard stock cards
            // aggregate — bump the dashboard cache version too so the main
            // page reflects the new stock at once instead of after the TTL.
            $this->dashboardCacheVersion->bumpShop($shopId);

            return $freshPurchase;
        });
    }

    /**
     * Edit an existing purchase. Mirrors SaleService::updateSale —
     * partial updates are honoured (no `items` key = items left alone),
     * and a full `items` swap atomically rolls back the old stock /
     * cost_price impact before applying the new lines.
     *
     * Owner-only on the route + policy; sellers are gated out by
     * ApiPermissionMatrix and PurchasePolicy.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePurchase(Purchase $purchase, User $actor, array $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $actor, $data): Purchase {
            $shopId = (int) $purchase->shop_id;

            $purchase->loadMissing('items');

            $itemsChanged = array_key_exists('items', $data);
            $totalAmount = $itemsChanged ? 0.0 : (float) $purchase->total_amount;

            if ($itemsChanged) {
                $this->rollbackPurchaseStock($purchase);
                $purchase->items()->delete();

                $items = $data['items'] ?? [];
                $productIds = collect($items)->pluck('product_id')->unique()->values();

                $products = $productIds->isNotEmpty()
                    ? $this->purchases
                        ->queryProductsForShop($actor, $shopId)
                        ->whereIn('id', $productIds)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id')
                    : collect();

                foreach ($items as $index => $item) {
                    $productId = $item['product_id'];
                    $product = $products->get($productId);

                    if (! $product) {
                        throw ValidationException::withMessages([
                            "items.{$index}.product_id" => ['Товар не найден или относится к другому магазину. Обновите каталог и попробуйте снова.'],
                        ]);
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
            }

            $purchase->update([
                'supplier_name' => array_key_exists('supplier_name', $data) ? $data['supplier_name'] : $purchase->supplier_name,
                'total_amount' => $totalAmount,
            ]);

            $purchase->increment('version');

            $fresh = $purchase->fresh(['items.product']);

            $this->auditLogger->log('purchases.updated', $actor, $fresh, [
                'supplier_name' => $fresh->supplier_name,
                'items_count' => $fresh->items->count(),
                'total_amount' => (float) $fresh->total_amount,
            ], $shopId);

            $this->productCatalogCache->bumpShop($shopId);
            // Purchases change stock / cost, which the dashboard stock cards
            // aggregate — bump the dashboard cache version too so the main
            // page reflects the new stock at once instead of after the TTL.
            $this->dashboardCacheVersion->bumpShop($shopId);

            return $fresh;
        });
    }

    /**
     * Soft-delete the purchase and roll back its stock impact. We do NOT
     * touch the product's `cost_price` / `pricing_mode` — those reflect
     * the most recent landed cost and aren't safely reversible (later
     * sales may have priced off them). Mirrors SaleService::deleteSale.
     */
    public function deletePurchase(Purchase $purchase, User $actor): void
    {
        DB::transaction(function () use ($purchase, $actor): void {
            $shopId = (int) $purchase->shop_id;

            $purchase->loadMissing('items');
            $this->rollbackPurchaseStock($purchase);

            $purchase->increment('version');
            $purchase->delete();

            $this->auditLogger->log('purchases.deleted', $actor, $purchase, [
                'supplier_name' => $purchase->supplier_name,
                'total_amount' => (float) $purchase->total_amount,
                'items_count' => $purchase->items->count(),
            ], $shopId);

            $this->productCatalogCache->bumpShop($shopId);
            // Purchases change stock / cost, which the dashboard stock cards
            // aggregate — bump the dashboard cache version too so the main
            // page reflects the new stock at once instead of after the TTL.
            $this->dashboardCacheVersion->bumpShop($shopId);
        });
    }

    /**
     * Reverse the stock additions made by a purchase's existing line
     * items. Locks the products for update; touches the version column
     * so dependent caches drop their entries. Used by both updatePurchase
     * (before replacing items) and deletePurchase.
     */
    private function rollbackPurchaseStock(Purchase $purchase): void
    {
        $productIds = $purchase->items->pluck('product_id')->filter()->unique()->values();

        if ($productIds->isEmpty()) {
            return;
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($purchase->items as $item) {
            $product = $products->get($item->product_id);
            if (! $product || ! $item->quantity) {
                continue;
            }
            $product->decrement('stock_quantity', (float) $item->quantity);
            DB::table('products')->where('id', $product->id)->increment('version');
        }
    }
}
