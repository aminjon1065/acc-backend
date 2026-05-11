<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Aggregate refunded money + per-item refunded quantity. Computed on
        // every read but the row count is bounded by SaleReturnItem rows for
        // this sale (rare beyond a handful) so the cost is negligible. Mobile
        // uses these to show a "Возврат" badge and to clamp the return modal
        // so the user can't request more than what's still refundable.
        $returnRows = $this->resource->returns()
            ->with('items:sale_return_id,product_id,quantity,total')
            ->get();
        $returnedTotal = (float) $returnRows->sum('total');
        $returnedByProduct = $returnRows
            ->flatMap(fn ($r) => $r->items)
            ->groupBy('product_id')
            ->map(fn ($group) => (float) $group->sum('quantity'));

        $items = $this->whenLoaded('items', function () use ($returnedByProduct) {
            return $this->items->map(function ($item) use ($returnedByProduct) {
                $returnedQty = (float) ($returnedByProduct[$item->product_id] ?? 0);

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'product_name' => $item->product?->name ?? $item->name,
                    'service_name' => $item->product_id === null ? $item->name : null,
                    'quantity' => (float) $item->quantity,
                    'price' => (float) $item->price,
                    'cost_price' => (float) $item->cost_price,
                    'total' => (float) $item->total,
                    // Quantity of this product already refunded across all
                    // SaleReturn rows for this sale. Mobile clamps the return
                    // modal at `quantity - returned_quantity`.
                    'returned_quantity' => $returnedQty,
                ];
            })->values();
        });

        $isFullyReturned = $this->relationLoaded('items')
            && $this->items->isNotEmpty()
            && $this->items->every(
                fn ($item) => (float) ($returnedByProduct[$item->product_id] ?? 0) >= (float) $item->quantity,
            );

        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'user_id' => $this->user_id,
            // Seller display name — populated only when the controller eager-
            // loaded `user`. Mobile renders this in the sale list + detail
            // so cashiers / owners can see who rang up the receipt without
            // a second user lookup.
            'seller_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'customer_name' => $this->customer_name,
            'type' => $this->type,
            'discount' => (float) $this->discount,
            'paid' => (float) $this->paid,
            'debt' => (float) $this->debt,
            'total' => (float) $this->total,
            'payment_type' => $this->payment_type,
            'notes' => $this->notes,
            'items' => $items,
            // Sum of money refunded against this sale (across all returns).
            // 0 when nothing was returned. Mobile uses this to show a badge.
            'returned_total' => $returnedTotal,
            // True when every line has been fully returned. Only meaningful
            // when `items` was loaded — otherwise we can't compare per-line.
            'is_fully_returned' => $isFullyReturned,
            '_local_id' => $this->when($request->input('_local_id'), $request->input('_local_id')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'version' => $this->version ?? 1,
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
