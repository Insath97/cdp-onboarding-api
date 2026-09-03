<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateZonalRequest;
use App\Http\Requests\UpdateZonalRequest;
use App\Models\Zonal;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ZonalController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Zonal Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Zonal Create', ['only' => ['store']]),
            new Middleware('permission:Zonal Update', ['only' => ['update']]),
            new Middleware('permission:Zonal Delete', ['only' => ['destroy']]),
            new Middleware('permission:Zonal Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of zonals.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Zonal::with('province.country');

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('province_id')) {
                $query->where('province_id', $request->province_id);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $zonals = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Zonals retrieved successfully',
                'data' => $zonals,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve zonals',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created zonal in storage.
     */
    public function store(CreateZonalRequest $request)
    {
        try {
            $data = $request->validated();
            $zonal = Zonal::create($data);

            $this->logActivity('CREATE', 'Zonal', "Created zonal: {$zonal->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Zonal created successfully',
                'data' => $zonal->load('province.country'),
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create zonal',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified zonal.
     */
    public function show(string $id)
    {
        try {
            $zonal = Zonal::with('province.country')->find($id);

            if (! $zonal) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Zonal not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Zonal retrieved successfully',
                'data' => $zonal,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve zonal',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified zonal in storage.
     */
    public function update(UpdateZonalRequest $request, string $id)
    {
        try {
            $zonal = Zonal::find($id);

            if (! $zonal) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Zonal not found',
                ], 404);
            }

            $data = $request->validated();
            $zonal->update($data);

            $this->logActivity('UPDATE', 'Zonal', "Updated zonal: {$zonal->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Zonal updated successfully',
                'data' => $zonal->load('province.country'),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update zonal',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified zonal from storage.
     */
    public function destroy(string $id)
    {
        try {
            $zonal = Zonal::find($id);

            if (! $zonal) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Zonal not found',
                ], 404);
            }

            $zonalName = $zonal->name;
            $zonal->delete();

            $this->logActivity('DELETE', 'Zonal', "Deleted zonal: {$zonalName}");

            return response()->json([
                'status' => 'success',
                'message' => 'Zonal deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete zonal',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a list of zonals (lightweight list).
     */
    public function getZonalList(Request $request)
    {
        try {
            $query = Zonal::active();

            if ($request->has('province_id')) {
                $query->where('province_id', $request->province_id);
            }

            $zonals = $query->orderBy('name', 'asc')->get(['id', 'name', 'code', 'province_id']);

            return response()->json([
                'status' => 'success',
                'message' => 'Zonals retrieved successfully',
                'data' => $zonals,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve zonals',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function toggleStatus(string $id)
    {
        try {
            $zonal = Zonal::find($id);

            if (!$zonal) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Zonal not found'
                ], 404);
            }

            $zonal->is_active = !$zonal->is_active;
            $zonal->save();

            $this->logActivity('TOGGLE_STATUS', 'Zonal', "Toggled zonal status: {$zonal->name} (" . ($zonal->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'Zonal status updated successfully',
                'data' => [
                    'id' => $zonal->id,
                    'is_active' => $zonal->is_active
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle zonal status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
