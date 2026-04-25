<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
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
            'supplier_name' => $this->supplier_name,
            'total' => (float) $this->total_amount,
            'total_amount' => (float) $this->total_amount,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'quantity' => (float) $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                ])->values();
            }),
            '_local_id' => $this->when($request->input('_local_id'), $request->input('_local_id')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
