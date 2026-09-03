<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Group;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class GroupController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Group Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Group Create', ['only' => ['store']]),
            new Middleware('permission:Group Update', ['only' => ['update']]),
            new Middleware('permission:Group Delete', ['only' => ['destroy']]),
            new Middleware('permission:Group Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of groups.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Group::query();

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $groups = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Groups retrieved successfully',
                'data' => $groups,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve groups',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created group in storage.
     */
    public function store(CreateGroupRequest $request)
    {
        try {
            $data = $request->validated();
            $group = Group::create($data);

            $this->logActivity('CREATE', 'Group', "Created group: {$group->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Group created successfully',
                'data' => $group,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create group',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified group.
     */
    public function show(string $id)
    {
        try {
            $group = Group::find($id);

            if (! $group) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Group not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Group retrieved successfully',
                'data' => $group,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve group',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified group in storage.
     */
    public function update(UpdateGroupRequest $request, string $id)
    {
        try {
            $group = Group::find($id);

            if (! $group) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Group not found',
                ], 404);
            }

            $data = $request->validated();
            $group->update($data);

            $this->logActivity('UPDATE', 'Group', "Updated group: {$group->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Group updated successfully',
                'data' => $group,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update group',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified group from storage.
     */
    public function destroy(string $id)
    {
        try {
            $group = Group::find($id);

            if (! $group) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Group not found',
                ], 404);
            }

            $groupName = $group->name;
            $group->delete();

            $this->logActivity('DELETE', 'Group', "Deleted group: {$groupName}");

            return response()->json([
                'status' => 'success',
                'message' => 'Group deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete group',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a list of all active groups (lightweight list).
     */
    public function getActiveList()
    {
        try {
            $groups = Group::active()->orderBy('name', 'asc')->get(['id', 'name', 'code']);

            if ($groups->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No active groups found',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Active groups retrieved successfully',
                'data' => $groups,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active groups',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function toggleStatus(string $id)
    {
        try {
            $group = Group::find($id);

            if (!$group) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Group not found'
                ], 404);
            }

            $group->is_active = !$group->is_active;
            $group->save();

            $this->logActivity('TOGGLE_STATUS', 'Group', "Toggled group status: {$group->name} (" . ($group->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'Group status updated successfully',
                'data' => [
                    'id' => $group->id,
                    'is_active' => $group->is_active
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle group status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
