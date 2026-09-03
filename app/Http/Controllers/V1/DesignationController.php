<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateDesignationRequest;
use App\Http\Requests\UpdateDesignationRequest;
use App\Models\Designation;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DesignationController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Designation Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Designation Create', ['only' => ['store']]),
            new Middleware('permission:Designation Update', ['only' => ['update']]),
            new Middleware('permission:Designation Delete', ['only' => ['destroy']]),
            new Middleware('permission:Designation Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of designations.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Designation::with('department');

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('department_id')) {
                $query->where('department_id', $request->department_id);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $designations = $query->orderBy('order_weight', 'desc')
                                  ->orderBy('name', 'asc')
                                  ->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Designations retrieved successfully',
                'data' => $designations,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve designations',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created designation in storage.
     */
    public function store(CreateDesignationRequest $request)
    {
        try {
            $data = $request->validated();
            $designation = Designation::create($data);

            $this->logActivity('CREATE', 'Designation', "Created designation: {$designation->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Designation created successfully',
                'data' => $designation->load('department'),
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create designation',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified designation.
     */
    public function show(string $id)
    {
        try {
            $designation = Designation::with('department')->find($id);

            if (! $designation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Designation not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Designation retrieved successfully',
                'data' => $designation,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve designation',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified designation in storage.
     */
    public function update(UpdateDesignationRequest $request, string $id)
    {
        try {
            $designation = Designation::find($id);

            if (! $designation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Designation not found',
                ], 404);
            }

            $data = $request->validated();
            
            // Force order_weight update if level changed and order_weight is not explicitly sent
            if (isset($data['level']) && $data['level'] !== $designation->level && !isset($data['order_weight'])) {
                $designation->order_weight = 0; // reset to let model boot handle it
            }
            
            $designation->update($data);

            $this->logActivity('UPDATE', 'Designation', "Updated designation: {$designation->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Designation updated successfully',
                'data' => $designation->load('department'),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update designation',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified designation from storage.
     */
    public function destroy(string $id)
    {
        try {
            $designation = Designation::find($id);

            if (! $designation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Designation not found',
                ], 404);
            }

            $designationName = $designation->name;
            $designation->delete();

            $this->logActivity('DELETE', 'Designation', "Deleted designation: {$designationName}");

            return response()->json([
                'status' => 'success',
                'message' => 'Designation deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete designation',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a list of designations (lightweight list).
     */
    public function getDesignationList(Request $request)
    {
        try {
            $query = Designation::active();

            if ($request->has('department_id')) {
                $query->where('department_id', $request->department_id);
            }

            $designations = $query->orderBy('order_weight', 'desc')
                                  ->orderBy('name', 'asc')
                                  ->get(['id', 'name', 'code', 'department_id', 'level', 'order_weight']);

            return response()->json([
                'status' => 'success',
                'message' => 'Designations retrieved successfully',
                'data' => $designations,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve designations',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function toggleStatus(string $id)
    {
        try {
            $designation = Designation::find($id);

            if (!$designation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Designation not found'
                ], 404);
            }

            $designation->is_active = !$designation->is_active;
            $designation->save();

            $this->logActivity('TOGGLE_STATUS', 'Designation', "Toggled designation status: {$designation->name} (" . ($designation->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'Designation status updated successfully',
                'data' => [
                    'id' => $designation->id,
                    'is_active' => $designation->is_active
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle designation status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
