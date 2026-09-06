<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCandidateRequest;
use App\Http\Requests\UpdateCandidateRequest;
use App\Models\Candidate;
use App\Models\ChannelHeadHistory;
use App\Traits\ActivityLogTrait;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CandidateController extends Controller implements HasMiddleware
{
    use ActivityLogTrait, FileUploadTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Candidate Index', ['only' => ['index', 'show', 'getCandidateList']]),
            new Middleware('permission:Candidate Create', ['only' => ['store']]),
            new Middleware('permission:Candidate Update', ['only' => ['update']]),
            new Middleware('permission:Candidate Delete', ['only' => ['destroy']]),
        ];
    }

    /**
     * Display a listing of candidates with search, filtering, and channel visibility scoping.
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $query = Candidate::with(['designation', 'uploader:id,name,email', 'channelHead:id,first_name,last_name']);
            $user = auth()->user();

            // Channel & Hierarchy visibility scoping (Super Admin bypasses restriction)
            if (! $user->hasRole('Super Admin')) {
                $employeeId = $user->employee_id;
                $query->where(function ($q) use ($user, $employeeId) {
                    $q->where('uploaded_by', $user->id);
                    if ($employeeId) {
                        $q->orWhere('channel_head_employee_id', $employeeId);
                    }
                });
            }

            // Keyword search filter
            if ($request->filled('search')) {
                $query->search($request->search);
            }

            // Status filter
            if ($request->filled('status')) {
                $query->status($request->status);
            }

            // Designation filter
            if ($request->filled('designation_id')) {
                $query->where('designation_id', $request->designation_id);
            }

            $candidates = $query->orderBy('created_at', 'desc')->paginate($perPage);

            $this->logActivity(
                'GET_ALL',
                'Candidate',
                'Retrieved candidate listing',
                $request->only(['search', 'status', 'designation_id', 'per_page', 'page'])
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Candidates retrieved successfully',
                'data' => $candidates,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve candidates',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly submitted candidate with CV upload.
     */
    public function store(CreateCandidateRequest $request)
    {
        try {
            $data = $request->validated();
            $user = auth()->user();

            // 1. Upload CV file using FileUploadTrait
            $cvPath = $this->handleFileUpload($request, 'cv_file', null, 'candidates', 'cv_' . time());
            if ($cvPath) {
                $data['cv_path'] = $cvPath;
            }

            // 2. Set submission parameters
            $data['status'] = 'pending';
            $data['submitted_at'] = now();
            $data['uploaded_by'] = $user->id;

            // 3. Resolve Channel Head snapshot for the submission channel
            $channelHeadEmployeeId = null;
            if ($user->employee) {
                $channelId = $user->employee->resolveChannel();
                if ($channelId) {
                    $headHistory = ChannelHeadHistory::where('channel_wise_employment_id', $channelId)
                                                     ->current()
                                                     ->first();
                    if ($headHistory) {
                        $channelHeadEmployeeId = $headHistory->employee_id;
                    }
                }
            }
            $data['channel_head_employee_id'] = $channelHeadEmployeeId;

            // 4. Save Candidate
            $candidate = Candidate::create($data);
            $candidate->load(['designation', 'uploader:id,name,email', 'channelHead:id,first_name,last_name']);

            $this->logActivity(
                'CREATE',
                'Candidate',
                "Submitted candidate CV: {$candidate->full_name} for designation ID {$candidate->designation_id}",
                [
                    'candidate_id' => $candidate->id,
                    'nic_number'   => $candidate->nic_number,
                    'phone_number' => $candidate->phone_number,
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Candidate submitted successfully',
                'data' => $candidate,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit candidate',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified candidate details.
     */
    public function show(string $id)
    {
        try {
            $candidate = Candidate::with(['designation', 'uploader:id,name,email', 'channelHead:id,first_name,last_name'])->find($id);

            if (! $candidate) {
                $this->logActivity('GET_BY_ID_FAILED', 'Candidate', "Candidate not found with ID: {$id}", ['id' => $id], 'warning');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Candidate not found',
                ], 404);
            }

            // Access check for non-Super Admin users
            $user = auth()->user();
            if (! $user->hasRole('Super Admin')) {
                $employeeId = $user->employee_id;
                $isUploader = ($candidate->uploaded_by == $user->id);
                $isChannelHead = ($employeeId && $candidate->channel_head_employee_id == $employeeId);

                if (! $isUploader && ! $isChannelHead) {
                    $this->logActivity('UNAUTHORIZED_ACCESS', 'Candidate', "Unauthorized candidate access attempt on ID: {$id}", ['id' => $id], 'warning');

                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized access to candidate record',
                    ], 403);
                }
            }

            $this->logActivity('GET_BY_ID', 'Candidate', "Retrieved candidate details for: {$candidate->full_name}", ['candidate_id' => $candidate->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Candidate retrieved successfully',
                'data' => $candidate,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve candidate',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified candidate profile and optionally replace CV file.
     */
    public function update(UpdateCandidateRequest $request, string $id)
    {
        try {
            $candidate = Candidate::find($id);

            if (! $candidate) {
                $this->logActivity('UPDATE_FAILED', 'Candidate', "Candidate not found with ID: {$id}", ['id' => $id], 'warning');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Candidate not found',
                ], 404);
            }

            $data = $request->validated();

            // Replace CV file if new file uploaded using FileUploadTrait
            $cvPath = $this->handleFileUpload($request, 'cv_file', $candidate->cv_path, 'candidates', 'cv_' . time());
            if ($cvPath) {
                $data['cv_path'] = $cvPath;
            }

            $candidate->update($data);
            $candidate->load(['designation', 'uploader:id,name,email', 'channelHead:id,first_name,last_name']);

            $this->logActivity(
                'UPDATE',
                'Candidate',
                "Updated candidate profile: {$candidate->full_name}",
                ['candidate_id' => $candidate->id, 'updated_fields' => array_keys($data)]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Candidate updated successfully',
                'data' => $candidate,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update candidate',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified candidate and delete CV file from storage.
     */
    public function destroy(string $id)
    {
        try {
            $candidate = Candidate::find($id);

            if (! $candidate) {
                $this->logActivity('DELETE_FAILED', 'Candidate', "Candidate not found with ID: {$id}", ['id' => $id], 'warning');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Candidate not found',
                ], 404);
            }

            $candidateName = $candidate->full_name;

            // Delete CV file using FileUploadTrait
            $this->deleteFile($candidate->cv_path);

            $candidate->delete();

            $this->logActivity('DELETE', 'Candidate', "Deleted candidate: {$candidateName} (ID: {$id})", ['id' => $id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Candidate deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete candidate',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Lightweight candidate listing for frontend select boxes / dropdowns.
     */
    public function getCandidateList(Request $request)
    {
        try {
            $query = Candidate::query();
            $user = auth()->user();

            if (! $user->hasRole('Super Admin')) {
                $employeeId = $user->employee_id;
                $query->where(function ($q) use ($user, $employeeId) {
                    $q->where('uploaded_by', $user->id);
                    if ($employeeId) {
                        $q->orWhere('channel_head_employee_id', $employeeId);
                    }
                });
            }

            $candidates = $query->orderBy('first_name', 'asc')
                                ->get(['id', 'first_name', 'last_name', 'nic_number', 'designation_id']);

            $this->logActivity('GET_LIST', 'Candidate', 'Retrieved candidate select box list');

            return response()->json([
                'status' => 'success',
                'message' => 'Candidate list retrieved successfully',
                'data' => $candidates,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve candidate list',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
