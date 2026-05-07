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
        // Owner / super_admin can pass an explicit `shop_id` filter to scope
        // the dashboard to a single shop. Seller is forced to their assigned
        // shop. Owner without an explicit filter sees an aggregate across
        // all their owned shops; we encode that as `shopId = null` for the
        // cache key (the service layer reads `accessibleShopIds` to compute
        // the correct WHERE clause).
        $shopId = match (true) {
            $user->role === \App\UserRole::Seller => (int) $user->shop_id,
            $user->isSuperAdmin(), $user->role === \App\UserRole::Owner => isset($filters['shop_id']) ? (int) $filters['shop_id'] : null,
            default => null,
        };
        $sellerId = $user->role === \App\UserRole::Seller ? (int) $user->id : null;
        $period = $filters['period'] ?? 'day';
        $dateFrom = $filters['date_from'] ?? '__null__';
        $dateTo = $filters['date_to'] ?? '__null__';
        $date = $filters['date'] ?? '__null__';

        $version = $this->dashboardCacheVersion->versionForShop($shopId);
        $cacheKey = "dashboard:user_{$user->id}:shop_{$shopId}:seller_{$sellerId}:period_{$period}:from_{$dateFrom}:to_{$dateTo}:date_{$date}:v{$version}";

        // Sellers get fresh data always — their debts are user-scoped, cached data would be stale
        $data = $sellerId !== null
            ? $this->dashboardService->build($user, $filters)
            : \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($user, $filters) {
                return $this->dashboardService->build($user, $filters);
            });

        if ($sellerId !== null) {
            // Strip cost/profit data from Seller responses
            if (is_array($data)) {
                unset($data['period_cogs']);
                unset($data['period_profit']);
                unset($data['period_expenses_total']);
                unset($data['stock_total_cost']);
            } elseif (is_object($data)) {
                unset($data->period_cogs);
                unset($data->period_profit);
                unset($data->period_expenses_total);
                unset($data->stock_total_cost);
            }
        }

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $data,
        ]);
    }
}
