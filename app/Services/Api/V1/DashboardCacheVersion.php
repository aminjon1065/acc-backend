<?php

namespace App\Services\Api\V1;

use Illuminate\Support\Facades\Cache;

class DashboardCacheVersion
{
    private const VERSION_TTL_SECONDS = 2_592_000; // 30 days

    public function versionForShop(?int $shopId): int
    {
        return (int) Cache::remember(
            $this->versionKey($shopId),
            self::VERSION_TTL_SECONDS,
            fn (): int => 1
        );
    }

    public function bumpShop(?int $shopId): void
    {
        $this->bumpVersion($shopId);

        if ($shopId !== null) {
            $this->bumpVersion(null);
        }
    }

    private function versionKey(?int $shopId): string
    {
        $scope = $shopId === null ? 'all' : (string) $shopId;

        return "dashboard:version:shop:{$scope}";
    }

    private function bumpVersion(?int $shopId): void
    {
        $key = $this->versionKey($shopId);

        if (! Cache::has($key)) {
            Cache::put($key, 2, self::VERSION_TTL_SECONDS);

            return;
        }

        Cache::increment($key);
    }
}
