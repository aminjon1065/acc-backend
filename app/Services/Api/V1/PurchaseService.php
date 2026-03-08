<?php

namespace App\Services\Api\V1;

use App\Models\Purchase;
use App\Models\User;
use App\Repositories\Api\V1\PurchaseRepository;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(private readonly PurchaseRepository $purchases) {}

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

            $totalAmount = 0.0;

            foreach ($items as $item) {
                $product = $this->purchases
                    ->queryProductsForShop($actor, $shopId)
                    ->lockForUpdate()
                    ->findOrFail($item['product_id']);

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
                $totalAmount += $lineTotal;
            }

            $purchase->update(['total_amount' => $totalAmount]);

            return $purchase->fresh(['items.product']);
        });
    }
}
