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
            'type' => $this->type,
            'discount' => (float) $this->discount,
            'paid' => (float) $this->paid,
            'debt' => (float) $this->debt,
            'total' => (float) $this->total,
            'payment_type' => $this->payment_type,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
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
                ])->values();
            }),
            '_local_id' => $this->when($request->input('_local_id'), $request->input('_local_id')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'version' => $this->version ?? 1,
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
