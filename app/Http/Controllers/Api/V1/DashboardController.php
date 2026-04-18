<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DashboardRequest;
use App\Services\Api\V1\DashboardCacheVersion;
use App\Services\Api\V1\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly DashboardCacheVersion $dashboardCacheVersion,
    ) {}

    public function show(DashboardRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();
        $shopId = $user->isSuperAdmin() ? ($filters['shop_id'] ?? null) : $user->shop_id;
        $period = $filters['period'] ?? 'day';
        $dateFrom = $filters['date_from'] ?? 'null';
        $dateTo = $filters['date_to'] ?? 'null';
        $date = $filters['date'] ?? 'null';

        $version = $this->dashboardCacheVersion->versionForShop($shopId);
        $cacheKey = "dashboard:shop_{$shopId}:period_{$period}:from_{$dateFrom}:to_{$dateTo}:date_{$date}:v{$version}";

        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($user, $filters) {
            return $this->dashboardService->build($user, $filters);
        });

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $data,
        ]);
    }
}
