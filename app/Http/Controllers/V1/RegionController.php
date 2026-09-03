<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRegionRequest;
use App\Http\Requests\UpdateRegionRequest;
use App\Models\Region;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RegionController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Region Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Region Create', ['only' => ['store']]),
            new Middleware('permission:Region Update', ['only' => ['update']]),
            new Middleware('permission:Region Delete', ['only' => ['destroy']]),
            new Middleware('permission:Region Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of regions.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Region::with('zonal.province.country');

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('zonal_id')) {
                $query->where('zonal_id', $request->zonal_id);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $regions = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Regions retrieved successfully',
                'data' => $regions,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve regions',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created region in storage.
     */
    public function store(CreateRegionRequest $request)
    {
        try {
            $data = $request->validated();
            $region = Region::create($data);

            $this->logActivity('CREATE', 'Region', "Created region: {$region->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Region created successfully',
                'data' => $region->load('zonal.province.country'),
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create region',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified region.
     */
    public function show(string $id)
    {
        try {
            $region = Region::with('zonal.province.country')->find($id);

            if (! $region) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Region not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Region retrieved successfully',
                'data' => $region,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve region',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified region in storage.
     */
    public function update(UpdateRegionRequest $request, string $id)
    {
        try {
            $region = Region::find($id);

            if (! $region) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Region not found',
                ], 404);
            }

            $data = $request->validated();
            $region->update($data);

            $this->logActivity('UPDATE', 'Region', "Updated region: {$region->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Region updated successfully',
                'data' => $region->load('zonal.province.country'),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update region',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified region from storage.
     */
    public function destroy(string $id)
    {
        try {
            $region = Region::find($id);

            if (! $region) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Region not found',
                ], 404);
            }

            $regionName = $region->name;
            $region->delete();

            $this->logActivity('DELETE', 'Region', "Deleted region: {$regionName}");

            return response()->json([
                'status' => 'success',
                'message' => 'Region deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete region',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a list of regions (lightweight list).
     */
    public function getRegionList(Request $request)
    {
        try {
            $query = Region::active();

            if ($request->has('zonal_id')) {
                $query->where('zonal_id', $request->zonal_id);
            }

            $regions = $query->orderBy('name', 'asc')->get(['id', 'name', 'code', 'zonal_id']);

            return response()->json([
                'status' => 'success',
                'message' => 'Regions retrieved successfully',
                'data' => $regions,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve regions',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function toggleStatus(string $id)
    {
        try {
            $region = Region::find($id);

            if (!$region) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Region not found'
                ], 404);
            }

            $region->is_active = !$region->is_active;
            $region->save();

            $this->logActivity('TOGGLE_STATUS', 'Region', "Toggled region status: {$region->name} (" . ($region->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'Region status updated successfully',
                'data' => [
                    'id' => $region->id,
                    'is_active' => $region->is_active
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle region status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
