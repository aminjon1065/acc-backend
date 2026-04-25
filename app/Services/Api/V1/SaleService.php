<?php

namespace App\Services\Api\V1;

use App\Enums\PricingMode;
use App\Models\Debt;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Repositories\Api\V1\SaleRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private readonly SaleRepository $sales,
        private readonly AuditLogger $auditLogger,
        private readonly ProductCatalogCache $productCatalogCache,
        private readonly DashboardCacheVersion $dashboardCacheVersion,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function createSale(
        User $actor,
        int $shopId,
        ?string $customerName,
        string $type,
        float $discount,
        float $paid,
        string $paymentType,
        ?string $notes,
        array $items
    ): Sale {
        return DB::transaction(function () use ($actor, $shopId, $customerName, $type, $discount, $paid, $paymentType, $notes, $items): Sale {
            $sale = $this->sales->create([
                'shop_id' => $shopId,
                'user_id' => $actor->id,
                'customer_name' => $customerName,
                'type' => $type,
                'discount' => $discount,
                'paid' => $paid,
                'debt' => 0,
                'total' => 0,
                'payment_type' => $paymentType,
                'notes' => $notes,
            ]);

            $productIds = collect($items)->pluck('product_id')->filter()->unique()->values();

            $products = $productIds->isNotEmpty()
                ? $this->sales
                    ->queryProductsForShop($actor, $shopId)
                    ->whereIn('id', $productIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id')
                : collect();

            $subTotal = 0.0;

            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $quantity = (float) $item['quantity'];

                if ($productId) {
                    $product = $products->get($productId);

                    if (! $product) {
                        throw ValidationException::withMessages([
                            'items' => ["Product #{$productId} not found. Sync products before creating sales with them."],
                        ]);
                    }

                    $price = $this->resolveProductPrice($product, $quantity, $item);

                    if ((float) $product->stock_quantity < $quantity) {
                        throw ValidationException::withMessages([
                            'items' => ["Insufficient stock for product: {$product->name}"],
                        ]);
                    }

                    $lineTotal = $quantity * $price;
                    $costPrice = (float) $product->cost_price;

                    $product->lockForUpdate();
                    $product->decrement('stock_quantity', $quantity);
                } else {
                    $price = (float) ($item['price'] ?? 0);
                    $lineTotal = $quantity * $price;
                    $costPrice = 0.0;
                }

                $sale->items()->create([
                    'shop_id' => $shopId,
                    'product_id' => $productId,
                    'name' => $item['name'] ?? null,
                    'unit' => $item['unit'] ?? null,
                    'quantity' => $quantity,
                    'price' => $price,
                    'cost_price' => $costPrice,
                    'total' => $lineTotal,
                ]);

                $subTotal += $lineTotal;
            }

            if ($discount > $subTotal) {
                throw ValidationException::withMessages([
                    'discount' => ["Discount cannot exceed subtotal ({$subTotal})."],
                ]);
            }

            $total = max($subTotal - $discount, 0);
            $debt = max($total - $paid, 0);

            $sale->update([
                'total' => $total,
                'debt' => $debt,
            ]);

            if ($debt > 0 && $customerName !== null) {
                $existingDebt = Debt::query()
                    ->where('shop_id', $shopId)
                    ->where('person_name', $customerName)
                    ->where('direction', 'receivable')
                    ->first();

                if ($existingDebt) {
                    $existingDebt->increment('balance', $debt);
                    $existingDebt->transactions()->create([
                        'shop_id' => $shopId,
                        'user_id' => $actor->id,
                        'type' => 'give',
                        'amount' => $debt,
                        'note' => "Sale #{$sale->id}",
                    ]);
                    DB::table('debts')->where('id', $existingDebt->id)->increment('version');
                } else {
                    $newDebt = Debt::query()->create([
                        'shop_id' => $shopId,
                        'user_id' => $actor->id,
                        'person_name' => $customerName,
                        'direction' => 'receivable',
                        'balance' => $debt,
                    ]);
                    $newDebt->transactions()->create([
                        'shop_id' => $shopId,
                        'user_id' => $actor->id,
                        'type' => 'give',
                        'amount' => $debt,
                        'note' => "Sale #{$sale->id}",
                    ]);
                }
            }

            $freshSale = $sale->fresh(['items.product']);

            $this->auditLogger->log('sales.created', $actor, $freshSale, [
                'customer_name' => $customerName,
                'type' => $type,
                'items_count' => count($items),
                'discount' => $discount,
                'paid' => $paid,
                'total' => (float) $freshSale->total,
                'debt' => (float) $freshSale->debt,
                'payment_type' => $paymentType,
                'notes' => $notes,
            ], $shopId);

            $this->productCatalogCache->bumpShop($shopId);
            $this->dashboardCacheVersion->bumpShop($shopId);

            $sale->increment('version');

            return $freshSale;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function updateSale(Sale $sale, User $actor, array $data): Sale
    {
        return DB::transaction(function () use ($sale, $actor, $data): Sale {
            $oldProductIds = $sale->items->pluck('product_id')->filter()->unique()->values();
            $oldProducts = $oldProductIds->isNotEmpty()
                ? Product::query()->whereIn('id', $oldProductIds)->lockForUpdate()->get()->keyBy('id')
                : collect();

            // Restore stock for old items before deleting
            foreach ($sale->items as $oldItem) {
                if ($oldItem->product_id && $oldItem->quantity) {
                    $oldProducts->get($oldItem->product_id)?->increment('stock_quantity', (float) $oldItem->quantity);
                }
            }

            // Delete old items
            $sale->items()->delete();

            $items = $data['items'] ?? [];
            $shopId = $sale->shop_id;

            $productIds = collect($items)->pluck('product_id')->filter()->unique()->values();
            $products = $productIds->isNotEmpty()
                ? $this->sales
                    ->queryProductsForShop($actor, $shopId)
                    ->whereIn('id', $productIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id')
                : collect();

            $subTotal = 0.0;

            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $quantity = (float) ($item['quantity'] ?? 0);

                if ($productId) {
                    $product = $products->get($productId);

                    if (! $product) {
                        throw ValidationException::withMessages([
                            'items' => ["Product #{$productId} not found. Sync products before updating sales with them."],
                        ]);
                    }

                    if ((float) $product->stock_quantity < $quantity) {
                        throw ValidationException::withMessages([
                            'items' => ["Insufficient stock for product: {$product->name}"],
                        ]);
                    }

                    $price = $this->resolveProductPrice($product, $quantity, $item);
                    $lineTotal = $quantity * $price;
                    $costPrice = (float) $product->cost_price;

                    $product->lockForUpdate();
                    $product->decrement('stock_quantity', $quantity);

                    $sale->items()->create([
                        'shop_id' => $shopId,
                        'product_id' => $productId,
                        'name' => $item['name'] ?? null,
                        'unit' => $item['unit'] ?? null,
                        'quantity' => $quantity,
                        'price' => $price,
                        'cost_price' => $costPrice,
                        'total' => $lineTotal,
                    ]);

                    $subTotal += $lineTotal;
                } else {
                    $price = (float) ($item['price'] ?? 0);
                    $lineTotal = $quantity * $price;

                    $sale->items()->create([
                        'shop_id' => $shopId,
                        'product_id' => null,
                        'name' => $item['name'] ?? null,
                        'unit' => $item['unit'] ?? null,
                        'quantity' => $quantity,
                        'price' => $price,
                        'cost_price' => 0,
                        'total' => $lineTotal,
                    ]);

                    $subTotal += $lineTotal;
                }
            }

            $discount = (float) ($data['discount'] ?? 0);
            $paid = (float) ($data['paid'] ?? 0);
            $customerName = $data['customer_name'] ?? $sale->customer_name;

            if ($discount > $subTotal) {
                throw ValidationException::withMessages([
                    'discount' => ["Discount cannot exceed subtotal ({$subTotal})."],
                ]);
            }

            $total = max($subTotal - $discount, 0);
            $debt = max($total - $paid, 0);

            $sale->update([
                'customer_name' => $customerName,
                'discount' => $discount,
                'paid' => $paid,
                'debt' => $debt,
                'total' => $total,
                'payment_type' => $data['payment_type'] ?? $sale->payment_type,
                'notes' => $data['notes'] ?? $sale->notes,
            ]);
            $sale->increment('version');

            $this->auditLogger->log('sales.updated', $actor, $sale->fresh(['items.product']), [
                'customer_name' => $customerName,
                'discount' => $discount,
                'paid' => $paid,
                'total' => $total,
                'debt' => $debt,
            ], $shopId);

            $this->productCatalogCache->bumpShop($shopId);
            $this->dashboardCacheVersion->bumpShop($shopId);

            return $sale->fresh(['items.product']);
        });
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveProductPrice(\App\Models\Product $product, float $quantity, array $item): float
    {
        if (array_key_exists('price', $item) && $item['price'] !== null) {
            return (float) $item['price'];
        }

        $hasBulkPricing = $product->bulk_threshold > 0 && $product->bulk_price > 0;

        if ($hasBulkPricing && $quantity >= (float) $product->bulk_threshold) {
            return (float) $product->bulk_price;
        }

        return match ($product->pricing_mode) {
            PricingMode::Manual => throw ValidationException::withMessages([
                'items' => ["Manual price is required for product: {$product->name}"],
            ]),
            PricingMode::Markup => $product->markup_percent !== null
                ? round((float) $product->cost_price * (1 + ((float) $product->markup_percent / 100)), 2)
                : (float) $product->sale_price,
            PricingMode::Fixed => (float) $product->sale_price,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items  [{ product_id, quantity }]
     */
    public function returnSale(
        User $actor,
        Sale $sale,
        array $items,
        ?string $reason,
        string $refundMethod
    ): SaleReturn {
        return DB::transaction(function () use ($actor, $sale, $items, $reason, $refundMethod): SaleReturn {
            $shopId = (int) $sale->shop_id;

            $productIds = collect($items)->pluck('product_id')->filter()->unique()->values();

            $products = $productIds->isNotEmpty()
                ? $this->sales
                    ->queryProductsForShop($actor, $shopId)
                    ->whereIn('id', $productIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id')
                : collect();

            $saleItemPrices = $sale->items->keyBy('product_id')->map(fn ($i) => (float) $i->price);
            $saleItemQuantities = $sale->items->keyBy('product_id')->map(fn ($i) => (float) $i->quantity);

            $returnItemsData = [];
            $returnTotal = 0.0;

            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = (float) $item['quantity'];

                $product = $products->get($productId);

                if (! $product) {
                    throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(Product::class, $productId);
                }

                $originalQty = $saleItemQuantities->get($productId);
                if ($originalQty === null) {
                    throw ValidationException::withMessages([
                        'items' => ["Product #{$productId} was not in the original sale."],
                    ]);
                }
                if ($quantity > $originalQty) {
                    throw ValidationException::withMessages([
                        'items' => ["Return quantity ({$quantity}) exceeds original sale quantity ({$originalQty}) for product #{$productId}."],
                    ]);
                }

                $price = $saleItemPrices->get($productId) ?? (float) $product->sale_price;
                $lineTotal = $quantity * $price;

                $product->increment('stock_quantity', $quantity);

                $returnItemsData[] = [
                    'shop_id' => $shopId,
                    'product_id' => $productId,
                    'name' => $product->name,
                    'unit' => $product->unit,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $lineTotal,
                ];

                $returnTotal += $lineTotal;
            }

            $refundAmount = min($returnTotal, (float) $sale->paid);
            $originalDebt = (float) $sale->debt;

            $sale->update([
                'paid' => max((float) $sale->paid - $refundAmount, 0),
                'debt' => max((float) $sale->debt - $returnTotal, 0),
            ]);

            if ($refundMethod === 'offset_debt' && $sale->customer_name !== null && $originalDebt > 0) {
                $existingDebt = Debt::query()
                    ->where('shop_id', $shopId)
                    ->where('person_name', $sale->customer_name)
                    ->where('direction', 'receivable')
                    ->first();

                if ($existingDebt) {
                    $existingDebt->decrement('balance', $returnTotal);
                    $existingDebt->transactions()->create([
                        'shop_id' => $shopId,
                        'user_id' => $actor->id,
                        'type' => 'repay',
                        'amount' => $returnTotal,
                        'note' => "Return #{$sale->id}",
                    ]);
                    DB::table('debts')->where('id', $existingDebt->id)->increment('version');
                }
            }

            $returnRecord = $this->sales->createReturn([
                'shop_id' => $shopId,
                'sale_id' => $sale->id,
                'user_id' => $actor->id,
                'reason' => $reason,
                'refund_method' => $refundMethod,
                'total' => $returnTotal,
            ]);

            foreach ($returnItemsData as $itemData) {
                $returnRecord->items()->create($itemData);
            }

            $this->auditLogger->log('sales.returned', $actor, $returnRecord, [
                'sale_id' => $sale->id,
                'reason' => $reason,
                'refund_method' => $refundMethod,
                'items_count' => count($items),
                'total' => $returnTotal,
            ], $shopId);

            $this->productCatalogCache->bumpShop($shopId);

            return $returnRecord->fresh(['items']);
        });
    }
}
