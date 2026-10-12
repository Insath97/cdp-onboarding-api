<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DashboardController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Define middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Dashboard View', only: ['index']),
        ];
    }

    /**
     * Get aggregated dashboard statistics, pipeline metrics, and recent candidates.
     */
    public function index(): JsonResponse
    {
        try {
            $user = auth()->user();
            $metrics = $this->dashboardService->getMetrics($user);

            $this->logActivity('VIEW_DASHBOARD', 'Dashboard', 'Retrieved dashboard metrics and pipeline statistics');

            return response()->json([
                'status'  => 'success',
                'message' => 'Dashboard metrics retrieved successfully',
                'data'    => $metrics,
            ], 200);

        } catch (\Throwable $th) {
            $this->logActivity('ERROR', 'Dashboard', 'Failed to retrieve dashboard metrics: ' . $th->getMessage(), null, 'error');

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve dashboard metrics',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
