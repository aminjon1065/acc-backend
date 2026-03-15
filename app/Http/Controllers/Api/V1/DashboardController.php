<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DashboardRequest;
use App\Services\Api\V1\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function show(DashboardRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $this->dashboardService->build($request->user(), $request->validated()),
        ]);
    }
}
