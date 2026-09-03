<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BranchController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Branch Index', only: ['index', 'show', 'getBranchList']),
            new Middleware('permission:Branch Create', only: ['store']),
            new Middleware('permission:Branch Update', only: ['update']),
            new Middleware('permission:Branch Delete', only: ['destroy']),
            new Middleware('permission:Branch Toggle Status', only: ['toggleStatus']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Branch::with(['province', 'region', 'zonal']);

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('province_id')) {
                $query->where('province_id', $request->province_id);
            }

            if ($request->has('region_id')) {
                $query->where('region_id', $request->region_id);
            }

            if ($request->has('zone_id')) {
                $query->where('zone_id', $request->zone_id);
            }

            $branches = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Branches retrieved successfully',
                'data' => $branches
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve branches',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function store(CreateBranchRequest $request)
    {
        try {
            $data = $request->validated();
            $branch = Branch::create($data);

            $this->logActivity('CREATE', 'Branch', "Created branch: {$branch->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Branch created successfully',
                'data' => $branch
            ], 201);
        } catch (\Throwable $th) {
            $this->logActivity('CREATE_FAILED', 'Branch', "Failed to create branch: {$th->getMessage()}", $data, 'error');
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create branch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $branch = Branch::with(['province', 'region', 'zonal'])->find($id);

            if (!$branch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Branch retrieved successfully',
                'data' => $branch
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve branch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function update(UpdateBranchRequest $request, string $id)
    {
        try {
            $branch = Branch::find($id);

            if (!$branch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch not found'
                ], 404);
            }

            $data = $request->validated();
            $branch->update($data);

            $this->logActivity('UPDATE', 'Branch', "Updated branch: {$branch->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Branch updated successfully',
                'data' => $branch
            ], 200);
        } catch (\Throwable $th) {
            $this->logActivity('UPDATE_FAILED', 'Branch', "Failed to update branch with ID: {$id}", ['error' => $th->getMessage(), 'data' => $data], 'error');

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update branch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $branch = Branch::find($id);

            if (!$branch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch not found'
                ], 404);
            }

            // Check if user is Super Admin
            if (!Auth::user()->hasRole('Super Admin')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only Super Admin can delete branches'
                ], 403);
            }

            $branchName = $branch->name;
            $branch->delete();

            $this->logActivity('DELETE', 'Branch', "Deleted branch: {$branchName}");

            return response()->json([
                'status' => 'success',
                'message' => 'Branch deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            $this->logActivity('DELETE_FAILED', 'Branch', "Failed to delete branch with ID: {$id}", ['error' => $th->getMessage()], 'error');
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete branch',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function toggleStatus(string $id)
    {
        try {
            $branch = Branch::find($id);

            if (!$branch) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Branch not found'
                ], 404);
            }

            $branch->is_active = !$branch->is_active;
            $branch->save();

            $this->logActivity('TOGGLE_STATUS', 'Branch', "Toggled branch status: {$branch->name} (" . ($branch->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'Branch status updated successfully',
                'data' => [
                    'id' => $branch->id,
                    'is_active' => $branch->is_active
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle branch status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function getBranchList(Request $request)
    {
        try {
            $query = Branch::active();

            if ($request->has('province_id')) {
                $query->where('province_id', $request->province_id);
            }

            if ($request->has('region_id')) {
                $query->where('region_id', $request->region_id);
            }

            if ($request->has('zone_id')) {
                $query->where('zone_id', $request->zone_id);
            }

            $branches = $query->select('id', 'name', 'code', 'city', 'province_id', 'region_id', 'zone_id')
                ->with(['province:id,name', 'region:id,name', 'zonal:id,name'])
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Branches retrieved successfully',
                'data' => $branches
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve branches',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
