<?php

namespace App\Services\Api\V1;

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
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function createSale(
        User $actor,
        int $shopId,
        ?string $customerName,
        float $discount,
        float $paid,
        string $paymentType,
        array $items
    ): Sale {
        return DB::transaction(function () use ($actor, $shopId, $customerName, $discount, $paid, $paymentType, $items): Sale {
            $sale = $this->sales->create([
                'shop_id' => $shopId,
                'user_id' => $actor->id,
                'customer_name' => $customerName,
                'discount' => $discount,
                'paid' => $paid,
                'debt' => 0,
                'total' => 0,
                'payment_type' => $paymentType,
            ]);

            $subTotal = 0.0;

            foreach ($items as $item) {
                $product = $this->sales
                    ->queryProductsForShop($actor, $shopId)
                    ->lockForUpdate()
                    ->findOrFail($item['product_id']);

                $quantity = (float) $item['quantity'];
                $price = array_key_exists('price', $item)
                    ? (float) $item['price']
                    : (float) $product->sale_price;

                if ((float) $product->stock_quantity < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for product: {$product->name}"],
                    ]);
                }

                $lineTotal = $quantity * $price;

                $sale->items()->create([
                    'shop_id' => $shopId,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'cost_price' => (float) $product->cost_price,
                    'total' => $lineTotal,
                ]);

                $product->decrement('stock_quantity', $quantity);
                $subTotal += $lineTotal;
            }

            $total = max($subTotal - $discount, 0);
            $debt = max($total - $paid, 0);

            $sale->update([
                'total' => $total,
                'debt' => $debt,
            ]);

            $freshSale = $sale->fresh(['items.product']);

            $this->auditLogger->log('sales.created', $actor, $freshSale, [
                'customer_name' => $customerName,
                'items_count' => count($items),
                'discount' => $discount,
                'paid' => $paid,
                'total' => (float) $freshSale->total,
                'debt' => (float) $freshSale->debt,
                'payment_type' => $paymentType,
            ], $shopId);

            return $freshSale;
        });
    }
}
