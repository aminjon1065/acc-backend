<?php

namespace App\Http\Resources\Api\V1;

use App\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imageUrl = $this->image_path
            ? rtrim($request->getSchemeAndHttpHost(), '/').Storage::disk('public')->url($this->image_path)
            : null;

        $isSeller = $request->user()?->role === UserRole::Seller
            || (is_string($request->user()?->role) && $request->user()?->role === 'seller');

        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'name' => $this->name,
            'code' => $this->code,
            'unit' => $this->unit,
            'cost_price' => $isSeller ? null : (float) $this->cost_price,
            'sale_price' => (float) $this->sale_price,
            'pricing_mode' => $this->pricing_mode,
            'markup_percent' => $this->markup_percent !== null ? (float) $this->markup_percent : null,
            'bulk_price' => $this->bulk_price !== null ? (float) $this->bulk_price : null,
            'bulk_threshold' => $this->bulk_threshold !== null ? (int) $this->bulk_threshold : null,
            'stock_quantity' => (float) $this->stock_quantity,
            'low_stock_alert' => (float) $this->low_stock_alert,
            'is_low_stock' => (float) $this->stock_quantity <= (float) $this->low_stock_alert,
            'image_path' => $this->image_path,
            'image_url' => $imageUrl,
            'photo_url' => $imageUrl,
            '_local_id' => $this->when($request->input('_local_id'), $request->input('_local_id')),
            'version' => $this->version ?? 1,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
