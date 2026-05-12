<?php

namespace App\Services\Api\V1;

use App\Enums\PricingMode;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use App\Repositories\Api\V1\SaleRepository;
use App\Services\AuditLogger;
use App\UserRole;
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
        return DB::transaction(function () use ($actor, $shopId, $customerName, $discount, $paid, $paymentType, $notes, $items): Sale {
            // Derive `type` from the cart so a single sale can mix product
            // lines with service lines (e.g. TV + installation). The caller-
            // supplied `$type` is honoured only for service-only carts that
            // explicitly tag themselves; the moment any item carries a
            // `product_id`, we mark the whole sale as "product" so the UI
            // groups it with stock-affecting transactions. Pure services
            // stay tagged "service" so dashboards and the badge keep working.
            $hasProduct = collect($items)->contains(fn ($i) => ! empty($i['product_id'] ?? null));
            $derivedType = $hasProduct ? 'product' : 'service';

            $sale = $this->sales->create([
                'shop_id' => $shopId,
                'user_id' => $actor->id,
                'customer_name' => $customerName,
                'type' => $derivedType,
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

            foreach ($items as $index => $item) {
                $productId = $item['product_id'] ?? null;
                $quantity = (float) $item['quantity'];

                if ($productId) {
                    $product = $products->get($productId);

                    if (! $product) {
                        // Use the per-item `items.{i}.product_id` key so the
                        // mobile client's parser can identify which line
                        // failed and evict the ghost from its local cache.
                        // Hits when the product was hard-deleted or its
                        // shop_id no longer matches the sale's target shop.
                        throw ValidationException::withMessages([
                            "items.{$index}.product_id" => ['Товар не найден или относится к другому магазину. Обновите каталог и попробуйте снова.'],
                        ]);
                    }

                    $price = $this->resolveProductPrice($product, $quantity, $item, $index);

                    if ($actor->role === UserRole::Seller && $price < (float) $product->sale_price) {
                        throw ValidationException::withMessages([
                            "items.{$index}.price" => ["Цена для \"{$product->name}\" не может быть ниже прайса."],
                        ]);
                    }

                    if ((float) $product->stock_quantity < $quantity) {
                        throw ValidationException::withMessages([
                            "items.{$index}.quantity" => ["Недостаточно товара \"{$product->name}\" на складе (доступно {$product->stock_quantity})."],
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

            // Debts are now a standalone entity managed from the Debts tab.
            // The sale tracks its own `debt` amount for receipt-level
            // accounting, but no longer mints a `debts` row automatically —
            // operators decide whether the unpaid balance belongs to a
            // long-term debtor relationship.
            $freshSale = $sale->fresh(['items.product']);

            $this->auditLogger->log('sales.created', $actor, $freshSale, [
                'customer_name' => $customerName,
                'type' => $derivedType,
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
            $shopId = $sale->shop_id;

            // Partial-update guard: only touch items / stock when the
            // caller explicitly sent an `items` field. Without this, a
            // mobile PATCH that only changes `customer_name` would
            // silently wipe every line item (old behaviour defaulted
            // missing items to `[]`).
            $itemsChanged = array_key_exists('items', $data);
            $subTotal = $itemsChanged ? 0.0 : (float) $sale->total + (float) $sale->discount;

            if ($itemsChanged) {
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
                $productIds = collect($items)->pluck('product_id')->filter()->unique()->values();
                $products = $productIds->isNotEmpty()
                    ? $this->sales
                        ->queryProductsForShop($actor, $shopId)
                        ->whereIn('id', $productIds)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id')
                    : collect();

                foreach ($items as $index => $item) {
                    $productId = $item['product_id'] ?? null;
                    $quantity = (float) ($item['quantity'] ?? 0);

                    if ($productId) {
                        $product = $products->get($productId);

                        if (! $product) {
                            // See SaleService::createSale — same per-item key
                            // contract so the mobile client can recover.
                            throw ValidationException::withMessages([
                                "items.{$index}.product_id" => ['Товар не найден или относится к другому магазину. Обновите каталог и попробуйте снова.'],
                            ]);
                        }

                        if ((float) $product->stock_quantity < $quantity) {
                            throw ValidationException::withMessages([
                                "items.{$index}.quantity" => ["Недостаточно товара \"{$product->name}\" на складе (доступно {$product->stock_quantity})."],
                            ]);
                        }

                        $price = $this->resolveProductPrice($product, $quantity, $item, $index);
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
            }

            $discount = array_key_exists('discount', $data) ? (float) $data['discount'] : (float) $sale->discount;
            $paid = array_key_exists('paid', $data) ? (float) $data['paid'] : (float) $sale->paid;
            $customerName = array_key_exists('customer_name', $data) ? $data['customer_name'] : $sale->customer_name;

            if ($discount > $subTotal) {
                throw ValidationException::withMessages([
                    'discount' => ["Discount cannot exceed subtotal ({$subTotal})."],
                ]);
            }

            $total = max($subTotal - $discount, 0);
            $debt = max($total - $paid, 0);

            // Re-derive `type` whenever the items collection changes — see
            // createSale for the rationale. Editing a service-only sale into
            // one that includes a product line should bump it out of the
            // "service" bucket.
            $derivedTypeUpdate = $itemsChanged
                ? (collect($data['items'] ?? [])->contains(fn ($i) => ! empty($i['product_id'] ?? null)) ? 'product' : 'service')
                : null;

            $sale->update([
                'customer_name' => $customerName,
                'discount' => $discount,
                'paid' => $paid,
                'debt' => $debt,
                'total' => $total,
                'payment_type' => $data['payment_type'] ?? $sale->payment_type,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $sale->notes,
                ...($derivedTypeUpdate !== null ? ['type' => $derivedTypeUpdate] : []),
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
     * @param  int|string|null  $index  Item index in the request payload.
     *                                  Used to attach validation errors
     *                                  with `items.{i}.price` so the
     *                                  mobile client's per-item parser
     *                                  can map back to the cart row.
     */
    private function resolveProductPrice(\App\Models\Product $product, float $quantity, array $item, int|string|null $index = null): float
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
                ($index !== null ? "items.{$index}.price" : 'items') => ["Укажите цену для \"{$product->name}\" — у товара включён ручной ввод."],
            ]),
            PricingMode::Markup => $product->markup_percent !== null
                ? round((float) $product->cost_price * (1 + ((float) $product->markup_percent / 100)), 2)
                : (float) $product->sale_price,
            PricingMode::Fixed => (float) $product->sale_price,
        };
    }

    /**
     * Soft-delete the sale and roll the sold quantities back into stock.
     * Used by the mobile detail screen's "Удалить" action; gated to
     * super_admin / owner by SalePolicy::delete.
     *
     * SoftDeletes on the Sale model keeps the row + its items reachable
     * via `withTrashed()` so audit + dashboard back-history stays whole.
     * We still increment `version` and bump caches so subsequent index
     * pulls drop the row from listing.
     */
    public function deleteSale(Sale $sale, User $actor): void
    {
        DB::transaction(function () use ($sale, $actor): void {
            $shopId = (int) $sale->shop_id;

            // Restore stock — same shape as updateSale's old-item rollback.
            $productIds = $sale->items->pluck('product_id')->filter()->unique()->values();
            $products = $productIds->isNotEmpty()
                ? Product::query()->whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id')
                : collect();

            foreach ($sale->items as $item) {
                if ($item->product_id && $item->quantity) {
                    $products->get($item->product_id)?->increment('stock_quantity', (float) $item->quantity);
                }
            }

            $sale->increment('version');
            $sale->delete();

            $this->auditLogger->log('sales.deleted', $actor, $sale, [
                'customer_name' => $sale->customer_name,
                'total' => (float) $sale->total,
                'items_count' => $sale->items->count(),
            ], $shopId);

            $this->productCatalogCache->bumpShop($shopId);
            $this->dashboardCacheVersion->bumpShop($shopId);
        });
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

            // Lock the sale row so two concurrent return attempts can't both
            // pass the "already-returned" check and double-refund stock.
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $sale->loadMissing('items');

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

            // Tally how much of each product has ALREADY been returned for
            // this sale. Without this the check below only compares against
            // the original sale quantity, so the same sale could be refunded
            // any number of times — stock gets credited each pass and the
            // sale's `paid`/`debt` keep marching toward zero.
            $alreadyReturnedByProduct = SaleReturnItem::query()
                ->whereHas('saleReturn', fn ($q) => $q->where('sale_id', $sale->id))
                ->select('product_id')
                ->selectRaw('SUM(quantity) as total_qty')
                ->groupBy('product_id')
                ->pluck('total_qty', 'product_id')
                ->map(fn ($v) => (float) $v);

            $returnItemsData = [];
            $returnTotal = 0.0;

            foreach ($items as $index => $item) {
                $productId = $item['product_id'];
                $quantity = (float) $item['quantity'];

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ["Return quantity must be positive for product #{$productId}."],
                    ]);
                }

                $product = $products->get($productId);

                if (! $product) {
                    throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(Product::class, $productId);
                }

                $originalQty = $saleItemQuantities->get($productId);
                if ($originalQty === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ["Product #{$productId} was not in the original sale."],
                    ]);
                }
                $alreadyReturned = (float) ($alreadyReturnedByProduct[$productId] ?? 0);
                $remaining = $originalQty - $alreadyReturned;
                if ($quantity > $remaining) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ["Можно вернуть не более {$remaining} (уже возвращено {$alreadyReturned} из {$originalQty})."],
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

            $sale->update([
                'paid' => max((float) $sale->paid - $refundAmount, 0),
                'debt' => max((float) $sale->debt - $returnTotal, 0),
            ]);

            // The `offset_debt` refund method used to walk the receivable
            // Debt for the sale's customer and decrement it. That coupling
            // is gone — Debts are managed manually now. We still accept
            // the `offset_debt` value as a label in the return record so
            // historical receipts read correctly; no further side effect.

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
            $this->dashboardCacheVersion->bumpShop($shopId);

            return $returnRecord->fresh(['items']);
        });
    }
}
