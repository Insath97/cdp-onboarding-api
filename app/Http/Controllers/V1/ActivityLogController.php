<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ActivityLogController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Activity Log Index', ['only' => ['index', 'show']]),
        ];
    }

    /**
     * Display a listing of activity logs with filtering and pagination.
     */
    public function index(Request $request)
    {
        try {
            $perPage = min((int) $request->get('per_page', 15), 100);
            $query = ActivityLog::with('user:id,name,email');

            // Keyword search filter (description, module, action, ip_address)
            if ($request->filled('search')) {
                $query->search($request->search);
            }

            // Filter by specific module
            if ($request->filled('module')) {
                $query->where('module', $request->module);
            }

            // Filter by action type (CREATE, UPDATE, DELETE, etc.)
            if ($request->filled('action')) {
                $query->where('action', strtoupper($request->action));
            }

            // Filter by performing user ID
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by severity level (info, warning, error, critical)
            if ($request->filled('level')) {
                $query->where('level', strtolower($request->level));
            }

            // Filter by start date (created_at >= date_from)
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            // Filter by end date (created_at <= date_to)
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

            $this->logActivity(
                'GET_ALL',
                'ActivityLog',
                'Retrieved activity logs list',
                $request->only(['search', 'module', 'action', 'user_id', 'level', 'date_from', 'date_to', 'per_page', 'page'])
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Activity logs retrieved successfully',
                'data' => $logs,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve activity logs',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified activity log.
     */
    public function show(string $id)
    {
        try {
            $log = ActivityLog::with('user:id,name,email')->find($id);

            if (! $log) {
                $this->logActivity('GET_BY_ID_FAILED', 'ActivityLog', "Activity log not found with ID: {$id}", ['id' => $id], 'warning');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Activity log not found',
                ], 404);
            }

            $this->logActivity('GET_BY_ID', 'ActivityLog', "Retrieved activity log details for ID: {$id}", ['id' => $id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Activity log retrieved successfully',
                'data' => $log,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve activity log',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
