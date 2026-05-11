<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->role;

        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            // Multi-shop ownership: list of shop IDs this user owns. Always
            // present (empty array for non-owners) so the mobile client can
            // unconditionally read it without a null guard.
            'owned_shop_ids' => $this->resource->owned_shop_ids,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role instanceof \BackedEnum ? $role->value : $role,
            'pin_reset_required' => (bool) $this->pin_reset_required,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            // Soft-delete tombstone. Mobile clients delete the matching local
            // row when this is non-null on a delta-sync (`updated_since`)
            // response; live writes never see a non-null value because the
            // default scope hides trashed rows.
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
