<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCandidateRequest;
use App\Http\Requests\UpdateCandidateRequest;
use App\Http\Requests\Assign1stInterviewRequest;
use App\Http\Requests\Approve1stInterviewRequest;
use App\Http\Requests\Reject1stInterviewRequest;
use App\Models\Candidate;
use App\Models\ChannelHeadHistory;
use App\Models\InterviewApproval;
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
            new Middleware('permission:Candidate Index', ['only' => ['index', 'show', 'getCandidateList', 'getApprovals']]),
            new Middleware('permission:Candidate Create', ['only' => ['store']]),
            new Middleware('permission:Candidate Update', ['only' => ['update']]),
            new Middleware('permission:Candidate Delete', ['only' => ['destroy']]),
            new Middleware('permission:Candidate Assign 1st Interview', ['only' => ['assign1stInterview']]),
            new Middleware('permission:Candidate Approve 1st Interview', ['only' => ['approve1stInterview']]),
            new Middleware('permission:Candidate Reject 1st Interview', ['only' => ['reject1stInterview']]),
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

    /**
     * Assign/delegate the 1st interview to another user.
     */
    public function assign1stInterview(Assign1stInterviewRequest $request, string $id)
    {
        try {
            $candidate = Candidate::find($id);

            if (! $candidate) {
                $this->logActivity('ASSIGN_1ST_INTERVIEW_FAILED', 'Candidate', "Candidate not found with ID: {$id}", ['id' => $id], 'warning');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Candidate not found',
                ], 404);
            }

            if (! in_array($candidate->status, ['pending', '1st_interview'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Candidate cannot be assigned at current stage (status: {$candidate->status})",
                ], 422);
            }

            $data = $request->validated();
            $user = auth()->user();

            InterviewApproval::create([
                'candidate_id'        => $candidate->id,
                'actor_id'            => $user->id,
                'assigned_to_user_id' => $data['assigned_to_user_id'],
                'stage'               => '1st_interview',
                'action'              => 'assigned',
                'assigned_date'       => $data['assigned_date'],
                'comments'            => $data['comments'],
                'status'              => 'pending',
            ]);

            $candidate->status = '1st_interview';
            $candidate->stage_updated_at = now();
            $candidate->save();

            $candidate->load(['designation', 'uploader:id,name,email', 'channelHead:id,first_name,last_name', 'approvals.assignedToUser:id,name,email']);

            $this->logActivity(
                'ASSIGN_1ST_INTERVIEW',
                'Candidate',
                "Assigned 1st interview for candidate {$candidate->full_name} to user ID {$data['assigned_to_user_id']}",
                ['candidate_id' => $candidate->id, 'assigned_to_user_id' => $data['assigned_to_user_id']]
            );

            return response()->json([
                'status' => 'success',
                'message' => '1st interview assigned successfully',
                'data' => $candidate,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to assign 1st interview',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Approve 1st interview (candidate passes to 2nd interview stage).
     */
    public function approve1stInterview(Approve1stInterviewRequest $request, string $id)
    {
        try {
            $candidate = Candidate::find($id);

            if (! $candidate) {
                $this->logActivity('APPROVE_1ST_INTERVIEW_FAILED', 'Candidate', "Candidate not found with ID: {$id}", ['id' => $id], 'warning');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Candidate not found',
                ], 404);
            }

            if (! in_array($candidate->status, ['pending', '1st_interview'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => "1st interview cannot be approved for candidate with status: {$candidate->status}",
                ], 422);
            }

            $data = $request->validated();
            $user = auth()->user();

            InterviewApproval::create([
                'candidate_id'   => $candidate->id,
                'actor_id'       => $user->id,
                'stage'          => '1st_interview',
                'action'         => 'approved',
                'interview_date' => $data['interview_date'],
                'comments'       => $data['comments'],
                'status'         => 'completed',
            ]);

            $candidate->status = '1st_interview_passed';
            $candidate->stage_updated_at = now();
            $candidate->save();

            $candidate->load(['designation', 'uploader:id,name,email', 'channelHead:id,first_name,last_name', 'approvals.actor:id,name,email']);

            $this->logActivity(
                'APPROVE_1ST_INTERVIEW',
                'Candidate',
                "Approved 1st interview for candidate: {$candidate->full_name}",
                ['candidate_id' => $candidate->id, 'interview_date' => $data['interview_date']]
            );

            return response()->json([
                'status' => 'success',
                'message' => '1st interview approved successfully. Candidate passed to Stage 2.',
                'data' => $candidate,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to approve 1st interview',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Reject 1st interview (candidate fails 1st interview).
     */
    public function reject1stInterview(Reject1stInterviewRequest $request, string $id)
    {
        try {
            $candidate = Candidate::find($id);

            if (! $candidate) {
                $this->logActivity('REJECT_1ST_INTERVIEW_FAILED', 'Candidate', "Candidate not found with ID: {$id}", ['id' => $id], 'warning');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Candidate not found',
                ], 404);
            }

            if (in_array($candidate->status, ['1st_interview_passed', '1st_interview_rejected', 'final_approved', 'rejected'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Candidate cannot be rejected from status: {$candidate->status}",
                ], 422);
            }

            $data = $request->validated();
            $user = auth()->user();

            InterviewApproval::create([
                'candidate_id'   => $candidate->id,
                'actor_id'       => $user->id,
                'stage'          => '1st_interview',
                'action'         => 'rejected',
                'interview_date' => $data['interview_date'],
                'comments'       => $data['comments'],
                'status'         => 'rejected',
            ]);

            $candidate->status = '1st_interview_rejected';
            $candidate->stage_updated_at = now();
            $candidate->save();

            $candidate->load(['designation', 'uploader:id,name,email', 'channelHead:id,first_name,last_name', 'approvals.actor:id,name,email']);

            $this->logActivity(
                'REJECT_1ST_INTERVIEW',
                'Candidate',
                "Rejected 1st interview for candidate: {$candidate->full_name}",
                ['candidate_id' => $candidate->id, 'interview_date' => $data['interview_date']],
                'warning'
            );

            return response()->json([
                'status' => 'success',
                'message' => '1st interview rejected successfully',
                'data' => $candidate,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reject 1st interview',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get full interview approval audit trail for a candidate.
     */
    public function getApprovals(string $id)
    {
        try {
            $candidate = Candidate::find($id);

            if (! $candidate) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Candidate not found',
                ], 404);
            }

            $approvals = InterviewApproval::with(['actor:id,name,email', 'assignedToUser:id,name,email'])
                                          ->where('candidate_id', $id)
                                          ->orderBy('created_at', 'asc')
                                          ->get();

            $this->logActivity('GET_APPROVALS', 'Candidate', "Retrieved interview approval history for candidate ID: {$id}", ['candidate_id' => $id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Candidate approval history retrieved successfully',
                'data' => $approvals,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve approval history',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
