<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => $this->name,
            'code' => $this->code,
            'unit' => $this->unit,
            'cost_price' => (float) $this->cost_price,
            'sale_price' => (float) $this->sale_price,
            'stock_quantity' => (float) $this->stock_quantity,
            'low_stock_alert' => (float) $this->low_stock_alert,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
