<?php

namespace App\Services\Api\V1;

use App\Models\Debt;
use App\Models\Sale;
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
                        throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(\App\Models\Product::class, $productId);
                    }

                    $price = $this->resolveProductPrice($product, $quantity, $item);

                    if ((float) $product->stock_quantity < $quantity) {
                        throw ValidationException::withMessages([
                            'items' => ["Insufficient stock for product: {$product->name}"],
                        ]);
                    }

                    $lineTotal = $quantity * $price;
                    $costPrice = (float) $product->cost_price;

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

            return $freshSale;
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
            'manual' => throw ValidationException::withMessages([
                'items' => ["Manual price is required for product: {$product->name}"],
            ]),
            'markup' => $product->markup_percent !== null
                ? round((float) $product->cost_price * (1 + ((float) $product->markup_percent / 100)), 2)
                : (float) $product->sale_price,
            default => (float) $product->sale_price,
        };
    }
}
