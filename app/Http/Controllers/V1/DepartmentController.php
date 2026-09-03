<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DepartmentController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Department Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Department Create', ['only' => ['store']]),
            new Middleware('permission:Department Update', ['only' => ['update']]),
            new Middleware('permission:Department Delete', ['only' => ['destroy']]),
            new Middleware('permission:Department Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of departments.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Department::with('head');

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $departments = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Departments retrieved successfully',
                'data' => $departments,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve departments',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created department in storage.
     */
    public function store(CreateDepartmentRequest $request)
    {
        try {
            $data = $request->validated();
            $department = Department::create($data);

            $this->logActivity('CREATE', 'Department', "Created department: {$department->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Department created successfully',
                'data' => $department->load('head'),
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create department',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified department.
     */
    public function show(string $id)
    {
        try {
            $department = Department::with(['head', 'designations'])->find($id);

            if (! $department) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Department not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Department retrieved successfully',
                'data' => $department,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve department',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified department in storage.
     */
    public function update(UpdateDepartmentRequest $request, string $id)
    {
        try {
            $department = Department::find($id);

            if (! $department) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Department not found',
                ], 404);
            }

            $data = $request->validated();
            $department->update($data);

            $this->logActivity('UPDATE', 'Department', "Updated department: {$department->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Department updated successfully',
                'data' => $department->load('head'),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update department',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified department from storage.
     */
    public function destroy(string $id)
    {
        try {
            $department = Department::find($id);

            if (! $department) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Department not found',
                ], 404);
            }

            $departmentName = $department->name;
            $department->delete();

            $this->logActivity('DELETE', 'Department', "Deleted department: {$departmentName}");

            return response()->json([
                'status' => 'success',
                'message' => 'Department deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete department',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a list of departments (lightweight list).
     */
    public function getDepartmentList()
    {
        try {
            $departments = Department::active()->orderBy('name', 'asc')->get(['id', 'name', 'code']);

            return response()->json([
                'status' => 'success',
                'message' => 'Departments retrieved successfully',
                'data' => $departments,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve departments',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function toggleStatus(string $id)
    {
        try {
            $department = Department::find($id);

            if (!$department) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Department not found'
                ], 404);
            }

            $department->is_active = !$department->is_active;
            $department->save();

            $this->logActivity('TOGGLE_STATUS', 'Department', "Toggled department status: {$department->name} (" . ($department->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'Department status updated successfully',
                'data' => [
                    'id' => $department->id,
                    'is_active' => $department->is_active
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle department status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
