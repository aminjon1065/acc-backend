<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebtResource extends JsonResource
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
            'person_name' => $this->person_name,
            'direction' => $this->direction,
            'balance' => (float) $this->balance,
            'transactions' => $this->whenLoaded('transactions', function () {
                return $this->transactions->map(fn ($transaction) => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => (float) $transaction->amount,
                    'note' => $transaction->note,
                    'created_at' => $transaction->created_at?->toISOString(),
                ])->values();
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
