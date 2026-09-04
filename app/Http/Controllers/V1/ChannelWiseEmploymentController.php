<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateChannelWiseEmploymentRequest;
use App\Http\Requests\UpdateChannelWiseEmploymentRequest;
use App\Models\ChannelWiseEmployment;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ChannelWiseEmploymentController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Channel Wise Employment Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Channel Wise Employment Create', ['only' => ['store']]),
            new Middleware('permission:Channel Wise Employment Update', ['only' => ['update']]),
            new Middleware('permission:Channel Wise Employment Delete', ['only' => ['destroy']]),
            new Middleware('permission:Channel Wise Employment Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of channel wise employments.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = ChannelWiseEmployment::query();

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $employments = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Channel wise employments retrieved successfully',
                'data' => $employments,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve channel wise employments',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created channel wise employment in storage.
     */
    public function store(CreateChannelWiseEmploymentRequest $request)
    {
        try {
            $data = $request->validated();
            $employment = ChannelWiseEmployment::create($data);

            $this->logActivity('CREATE', 'ChannelWiseEmployment', "Created channel wise employment: {$employment->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Channel wise employment created successfully',
                'data' => $employment,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create channel wise employment',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified channel wise employment.
     */
    public function show(string $id)
    {
        try {
            $employment = ChannelWiseEmployment::find($id);

            if (! $employment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Channel wise employment not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Channel wise employment retrieved successfully',
                'data' => $employment,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve channel wise employment',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified channel wise employment in storage.
     */
    public function update(UpdateChannelWiseEmploymentRequest $request, string $id)
    {
        try {
            $employment = ChannelWiseEmployment::find($id);

            if (! $employment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Channel wise employment not found',
                ], 404);
            }

            $data = $request->validated();
            $employment->update($data);

            $this->logActivity('UPDATE', 'ChannelWiseEmployment', "Updated channel wise employment: {$employment->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Channel wise employment updated successfully',
                'data' => $employment,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update channel wise employment',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified channel wise employment from storage.
     */
    public function destroy(string $id)
    {
        try {
            $employment = ChannelWiseEmployment::find($id);

            if (! $employment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Channel wise employment not found',
                ], 404);
            }

            $employmentName = $employment->name;
            $employment->delete();

            $this->logActivity('DELETE', 'ChannelWiseEmployment', "Deleted channel wise employment: {$employmentName}");

            return response()->json([
                'status' => 'success',
                'message' => 'Channel wise employment deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete channel wise employment',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a list of all active channel wise employments (lightweight list).
     */
    public function getActiveList()
    {
        try {
            $employments = ChannelWiseEmployment::active()->orderBy('name', 'asc')->get(['id', 'name']);

            return response()->json([
                'status' => 'success',
                'message' => 'Active channel wise employments retrieved successfully',
                'data' => $employments,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active channel wise employments',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the active status of the specified channel wise employment.
     */
    public function toggleStatus(string $id)
    {
        try {
            $employment = ChannelWiseEmployment::find($id);

            if (! $employment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Channel wise employment not found',
                ], 404);
            }

            $employment->is_active = ! $employment->is_active;
            $employment->save();

            $this->logActivity('TOGGLE_STATUS', 'ChannelWiseEmployment', "Toggled channel wise employment status: {$employment->name} (" . ($employment->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'Channel wise employment status updated successfully',
                'data' => [
                    'id' => $employment->id,
                    'is_active' => $employment->is_active,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle channel wise employment status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
