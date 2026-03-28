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
        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'user_id' => $this->user_id,
            'customer_name' => $this->customer_name,
            'discount' => (float) $this->discount,
            'paid' => (float) $this->paid,
            'debt' => (float) $this->debt,
            'total' => (float) $this->total,
            'payment_type' => $this->payment_type,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'product_name' => $item->product?->name ?? $item->name,
                    'quantity' => (float) $item->quantity,
                    'price' => (float) $item->price,
                    'cost_price' => (float) $item->cost_price,
                    'total' => (float) $item->total,
                ])->values();
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
