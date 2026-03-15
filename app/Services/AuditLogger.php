<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $event,
        ?User $actor = null,
        ?Model $auditable = null,
        array $metadata = [],
        ?int $shopId = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'shop_id' => $shopId ?? $actor?->shop_id ?? $auditable?->getAttribute('shop_id'),
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
