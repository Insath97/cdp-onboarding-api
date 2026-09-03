<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProvinceRequest;
use App\Http\Requests\UpdateProvinceRequest;
use App\Models\Province;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProvinceController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Province Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Province Create', ['only' => ['store']]),
            new Middleware('permission:Province Update', ['only' => ['update']]),
            new Middleware('permission:Province Delete', ['only' => ['destroy']]),
            new Middleware('permission:Province Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of provinces.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Province::with('country');

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('country_id')) {
                $query->where('country_id', $request->country_id);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $provinces = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Provinces retrieved successfully',
                'data' => $provinces,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve provinces',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created province in storage.
     */
    public function store(CreateProvinceRequest $request)
    {
        try {
            $data = $request->validated();
            $province = Province::create($data);

            $this->logActivity('CREATE', 'Province', "Created province: {$province->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Province created successfully',
                'data' => $province->load('country'),
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create province',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified province.
     */
    public function show(string $id)
    {
        try {
            $province = Province::with('country')->find($id);

            if (! $province) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Province not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Province retrieved successfully',
                'data' => $province,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve province',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified province in storage.
     */
    public function update(UpdateProvinceRequest $request, string $id)
    {
        try {
            $province = Province::find($id);

            if (! $province) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Province not found',
                ], 404);
            }

            $data = $request->validated();
            $province->update($data);

            $this->logActivity('UPDATE', 'Province', "Updated province: {$province->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Province updated successfully',
                'data' => $province->load('country'),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update province',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified province from storage.
     */
    public function destroy(string $id)
    {
        try {
            $province = Province::find($id);

            if (! $province) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Province not found',
                ], 404);
            }

            $provinceName = $province->name;
            $province->delete();

            $this->logActivity('DELETE', 'Province', "Deleted province: {$provinceName}");

            return response()->json([
                'status' => 'success',
                'message' => 'Province deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete province',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a list of provinces (lightweight list).
     */
    public function getProvinceList(Request $request)
    {
        try {
            $query = Province::active();

            if ($request->has('country_id')) {
                $query->where('country_id', $request->country_id);
            }

            $provinces = $query->orderBy('name', 'asc')->get(['id', 'name', 'code', 'country_id']);

            return response()->json([
                'status' => 'success',
                'message' => 'Provinces retrieved successfully',
                'data' => $provinces,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve provinces',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function toggleStatus(string $id)
    {
        try {
            $province = Province::find($id);

            if (!$province) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Province not found'
                ], 404);
            }

            $province->is_active = !$province->is_active;
            $province->save();

            $this->logActivity('TOGGLE_STATUS', 'Province', "Toggled province status: {$province->name} (" . ($province->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'Province status updated successfully',
                'data' => [
                    'id' => $province->id,
                    'is_active' => $province->is_active
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle province status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
