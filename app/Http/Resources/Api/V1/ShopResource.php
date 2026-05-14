<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopResource extends JsonResource
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
            'name' => $this->name,
            // Multi-shop ownership: which user owns this shop. Null when
            // unassigned. Mobile clients use this to populate the "Владелец"
            // edit dropdown and decide whether the shop is reachable.
            'owner_id' => $this->owner_id,
            // Prefer the related user's name (set via the owner dropdown) and
            // fall back to the legacy free-form `owner_name` text column for
            // historical imports that pre-date multi-shop ownership.
            'owner_name' => $this->owner?->name ?? $this->owner_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
