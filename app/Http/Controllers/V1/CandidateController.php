<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCandidateRequest;
use App\Http\Requests\UpdateCandidateRequest;
use App\Http\Requests\Assign1stInterviewRequest;
use App\Http\Requests\Approve1stInterviewRequest;
use App\Http\Requests\Reject1stInterviewRequest;
use App\Http\Requests\Assign2ndInterviewRequest;
use App\Http\Requests\Approve2ndInterviewRequest;
use App\Http\Requests\Reject2ndInterviewRequest;
use App\Http\Requests\ApproveFinalRequest;
use App\Http\Requests\RejectFinalRequest;
use App\Models\Candidate;
use App\Models\ChannelHeadHistory;
use App\Models\InterviewApproval;
use App\Models\User;
use App\Notifications\CandidateSubmittedNotification;
use App\Notifications\CandidateInterviewAssignedNotification;
use App\Notifications\CandidateStageStatusNotification;
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
            new Middleware('permission:Candidate Index|Candidate Show|Candidate Assign 1st Interview|Candidate Approve 1st Interview|Candidate Assign 2nd Interview|Candidate Approve 2nd Interview|Candidate Approve Final', ['only' => ['index', 'show', 'getCandidateList', 'getApprovals', 'getInterviewers']]),
            new Middleware('permission:Candidate Create', ['only' => ['store']]),
            new Middleware('permission:Candidate Update', ['only' => ['update']]),
            new Middleware('permission:Candidate Delete', ['only' => ['destroy']]),
            new Middleware('permission:Candidate Assign 1st Interview', ['only' => ['assign1stInterview']]),
            new Middleware('permission:Candidate Approve 1st Interview', ['only' => ['approve1stInterview']]),
            new Middleware('permission:Candidate Reject 1st Interview', ['only' => ['reject1stInterview']]),
            new Middleware('permission:Candidate Assign 2nd Interview', ['only' => ['assign2ndInterview']]),
            new Middleware('permission:Candidate Approve 2nd Interview', ['only' => ['approve2ndInterview']]),
            new Middleware('permission:Candidate Reject 2nd Interview', ['only' => ['reject2ndInterview']]),
            new Middleware('permission:Candidate Approve Final', ['only' => ['approveFinal']]),
            new Middleware('permission:Candidate Reject Final', ['only' => ['rejectFinal']]),
        ];
    }

    /**
     * Display a listing of candidates with search, filtering, and channel/stage visibility scoping.
     */
    public function index(Request $request)
    {
        try {
            $perPage = min((int) $request->get('per_page', 15), 100);
            $user = auth()->user();
            $query = Candidate::with(['designation', 'uploader:id,name,email', 'channelHead:id,first_name,last_name'])
                              ->accessibleBy($user);

            // Keyword search filter
            if ($request->filled('search')) {
                $query->search($request->search);
            }

            // Status filter (supports single status or comma-separated list e.g. 1st_interview_passed,2nd_interview)
            if ($request->filled('status')) {
                $query->status($request->status);
            }

            // Workflow stage filter (1st_interview, 2nd_interview, final_approval)
            if ($request->filled('stage')) {
                $query->stage($request->stage);
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
                $request->only(['search', 'status', 'stage', 'designation_id', 'per_page', 'page'])
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

            // 5. Dispatch Notifications
            $user->notify(new CandidateSubmittedNotification($candidate));
            if ($channelHeadEmployeeId) {
                $headUser = User::where('employee_id', $channelHeadEmployeeId)->first();
                if ($headUser && $headUser->id !== $user->id) {
                    $headUser->notify(new CandidateSubmittedNotification($candidate));
                }
            }

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

            // Access check for non-Super Admin users (Channel & Parent/Child hierarchy scoping)
            $user = auth()->user();
            if (! $candidate->isAccessibleBy($user)) {
                $this->logActivity('UNAUTHORIZED_ACCESS', 'Candidate', "Unauthorized candidate access attempt on ID: {$id}", ['id' => $id], 'warning');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to candidate record outside your channel or hierarchy',
                ], 403);
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
            $user = auth()->user();
            $query = Candidate::accessibleBy($user);

            // Status filter (single or comma-separated)
            if ($request->filled('status')) {
                $query->status($request->status);
            }

            // Workflow stage filter (1st_interview, 2nd_interview, final_approval)
            if ($request->filled('stage')) {
                $query->stage($request->stage);
            }

            $candidates = $query->orderBy('first_name', 'asc')
                                ->get(['id', 'first_name', 'last_name', 'nic_number', 'designation_id', 'status']);

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
     * Get active users eligible for assignment as interviewers for a given workflow stage.
     */
    public function getInterviewers(Request $request)
    {
        try {
            $stage = $request->get('stage', '2nd_interview');

            $permission = match ($stage) {
                '1st_interview'  => 'Candidate Approve 1st Interview',
                '2nd_interview'  => 'Candidate Approve 2nd Interview',
                'final_approval' => 'Candidate Approve Final',
                default          => 'Candidate Approve 2nd Interview',
            };

            $query = User::where('is_active', true)
                ->where(function ($q) use ($permission) {
                    $q->permission($permission)
                      ->orWhereHas('roles', function ($rq) {
                          $rq->where('name', 'Super Admin');
                      });
                })
                ->with([
                    'employee:id,f_name,l_name,full_name,employee_code,designation_id,branch_id',
                    'employee.designation:id,name',
                    'employee.branch:id,name',
                    'roles:id,name',
                ]);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%")
                      ->orWhereHas('employee', function ($eq) use ($search) {
                          $eq->where('full_name', 'like', "%{$search}%")
                             ->orWhere('employee_code', 'like', "%{$search}%");
                      });
                });
            }

            $users = $query->orderBy('name', 'asc')->get();

            $data = $users->map(function ($u) {
                return [
                    'id'               => $u->id,
                    'name'             => $u->name,
                    'email'            => $u->email,
                    'username'         => $u->username,
                    'employee_id'      => $u->employee_id,
                    'designation_name' => $u->employee?->designation?->name,
                    'branch_name'      => $u->employee?->branch?->name,
                    'roles'            => $u->roles->pluck('name'),
                ];
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Interviewers retrieved successfully',
                'data'    => $data,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve interviewers',
                'error'   => config('app.debug') ? $th->getMessage() : 'Internal server error',
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

            $user = auth()->user();
            if (! $candidate->isAccessibleBy($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to candidate record outside your channel or hierarchy',
                ], 403);
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

            // Dispatch notification to assigned user
            $assignedUser = User::find($data['assigned_to_user_id']);
            if ($assignedUser) {
                $assignedUser->notify(new CandidateInterviewAssignedNotification($candidate, '1st_interview', $user, $data['assigned_date'], $data['comments']));
            }

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

            $user = auth()->user();
            if (! $candidate->isAccessibleBy($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to candidate record outside your channel or hierarchy',
                ], 403);
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

            // Dispatch notification to candidate uploader
            $uploader = User::find($candidate->uploaded_by);
            if ($uploader) {
                $uploader->notify(new CandidateStageStatusNotification($candidate, 'approved', '1st_interview', $user, $data['comments']));
            }

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

            $user = auth()->user();
            if (! $candidate->isAccessibleBy($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to candidate record outside your channel or hierarchy',
                ], 403);
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

            // Dispatch notification to candidate uploader
            $uploader = User::find($candidate->uploaded_by);
            if ($uploader) {
                $uploader->notify(new CandidateStageStatusNotification($candidate, 'rejected', '1st_interview', $user, $data['comments']));
            }

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

            $user = auth()->user();
            if (! $candidate->isAccessibleBy($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to candidate record outside your channel or hierarchy',
                ], 403);
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

    /**
     * Assign/delegate the 2nd interview to another user.
     */
    public function assign2ndInterview(Assign2ndInterviewRequest $request, string $id)
    {
        try {
            $candidate = Candidate::find($id);

            if (! $candidate) {
                $this->logActivity('ASSIGN_2ND_INTERVIEW_FAILED', 'Candidate', "Candidate not found with ID: {$id}", ['id' => $id], 'warning');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Candidate not found',
                ], 404);
            }

            $user = auth()->user();
            if (! $candidate->isAccessibleBy($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to candidate record outside your channel or hierarchy',
                ], 403);
            }

            if (! in_array($candidate->status, ['1st_interview_passed', '2nd_interview'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Candidate 2nd interview cannot be assigned at current status: {$candidate->status}",
                ], 422);
            }

            $data = $request->validated();

            InterviewApproval::create([
                'candidate_id'        => $candidate->id,
                'actor_id'            => $user->id,
                'assigned_to_user_id' => $data['assigned_to_user_id'],
                'stage'               => '2nd_interview',
                'action'              => 'assigned',
                'assigned_date'       => $data['assigned_date'],
                'comments'            => $data['comments'],
                'status'              => 'pending',
            ]);

            $candidate->status = '2nd_interview';
            $candidate->stage_updated_at = now();
            $candidate->save();

            // Dispatch notification to assigned user
            $assignedUser = User::find($data['assigned_to_user_id']);
            if ($assignedUser) {
                $assignedUser->notify(new CandidateInterviewAssignedNotification($candidate, '2nd_interview', $user, $data['assigned_date'], $data['comments']));
            }

            $candidate->load(['designation', 'uploader:id,name,email', 'channelHead:id,first_name,last_name', 'approvals.assignedToUser:id,name,email']);

            $this->logActivity(
                'ASSIGN_2ND_INTERVIEW',
                'Candidate',
                "Assigned 2nd interview for candidate {$candidate->full_name} to user ID {$data['assigned_to_user_id']}",
                ['candidate_id' => $candidate->id, 'assigned_to_user_id' => $data['assigned_to_user_id']]
            );

            return response()->json([
                'status' => 'success',
                'message' => '2nd interview assigned successfully',
                'data' => $candidate,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to assign 2nd interview',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Approve 2nd interview (candidate passes to Stage 3 Final Approval).
     */
    public function approve2ndInterview(Approve2ndInterviewRequest $request, string $id)
    {
        try {
            $candidate = Candidate::find($id);

            if (! $candidate) {
                $this->logActivity('APPROVE_2ND_INTERVIEW_FAILED', 'Candidate', "Candidate not found with ID: {$id}", ['id' => $id], 'warning');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Candidate not found',
                ], 404);
            }

            $user = auth()->user();
            if (! $candidate->isAccessibleBy($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to candidate record outside your channel or hierarchy',
                ], 403);
            }

            if ($candidate->status !== '2nd_interview') {
                return response()->json([
                    'status' => 'error',
                    'message' => "Candidate 2nd interview must be assigned before approval. Current status: {$candidate->status}",
                ], 422);
            }

            if (! $user->hasRole('Super Admin') && ! $candidate->isAssigned2ndInterviewer($user)) {
                $this->logActivity('APPROVE_2ND_INTERVIEW_UNAUTHORIZED', 'Candidate', "Unauthorized 2nd interview approval attempt on candidate ID: {$id} by user ID: {$user->id}", ['candidate_id' => $candidate->id], 'warning');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. Only the assigned interviewer or Super Admin can approve this 2nd interview.',
                ], 403);
            }

            $data = $request->validated();

            InterviewApproval::create([
                'candidate_id'   => $candidate->id,
                'actor_id'       => $user->id,
                'stage'          => '2nd_interview',
                'action'         => 'approved',
                'interview_date' => $data['interview_date'],
                'comments'       => $data['comments'],
                'status'         => 'completed',
            ]);

            $candidate->status = '2nd_interview_passed';
            $candidate->stage_updated_at = now();
            $candidate->save();

            // Dispatch notification to candidate uploader
            $uploader = User::find($candidate->uploaded_by);
            if ($uploader) {
                $uploader->notify(new CandidateStageStatusNotification($candidate, 'approved', '2nd_interview', $user, $data['comments']));
            }

            $candidate->load(['designation', 'uploader:id,name,email', 'channelHead:id,first_name,last_name', 'approvals.actor:id,name,email']);

            $this->logActivity(
                'APPROVE_2ND_INTERVIEW',
                'Candidate',
                "Approved 2nd interview for candidate: {$candidate->full_name}",
                ['candidate_id' => $candidate->id, 'interview_date' => $data['interview_date']]
            );

            return response()->json([
                'status' => 'success',
                'message' => '2nd interview approved successfully. Candidate passed to Stage 3 Final Approval.',
                'data' => $candidate,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to approve 2nd interview',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Reject 2nd interview (candidate fails 2nd interview).
     */
    public function reject2ndInterview(Reject2ndInterviewRequest $request, string $id)
    {
        try {
            $candidate = Candidate::find($id);

            if (! $candidate) {
                $this->logActivity('REJECT_2ND_INTERVIEW_FAILED', 'Candidate', "Candidate not found with ID: {$id}", ['id' => $id], 'warning');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Candidate not found',
                ], 404);
            }

            $user = auth()->user();
            if (! $candidate->isAccessibleBy($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to candidate record outside your channel or hierarchy',
                ], 403);
            }

            if ($candidate->status !== '2nd_interview') {
                return response()->json([
                    'status' => 'error',
                    'message' => "Candidate 2nd interview must be assigned before rejection. Current status: {$candidate->status}",
                ], 422);
            }

            if (! $user->hasRole('Super Admin') && ! $candidate->isAssigned2ndInterviewer($user)) {
                $this->logActivity('REJECT_2ND_INTERVIEW_UNAUTHORIZED', 'Candidate', "Unauthorized 2nd interview rejection attempt on candidate ID: {$id} by user ID: {$user->id}", ['candidate_id' => $candidate->id], 'warning');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. Only the assigned interviewer or Super Admin can reject this 2nd interview.',
                ], 403);
            }

            $data = $request->validated();

            InterviewApproval::create([
                'candidate_id'   => $candidate->id,
                'actor_id'       => $user->id,
                'stage'          => '2nd_interview',
                'action'         => 'rejected',
                'interview_date' => $data['interview_date'],
                'comments'       => $data['comments'],
                'status'         => 'rejected',
            ]);

            $candidate->status = '2nd_interview_rejected';
            $candidate->stage_updated_at = now();
            $candidate->save();

            // Dispatch notification to candidate uploader
            $uploader = User::find($candidate->uploaded_by);
            if ($uploader) {
                $uploader->notify(new CandidateStageStatusNotification($candidate, 'rejected', '2nd_interview', $user, $data['comments']));
            }

            $candidate->load(['designation', 'uploader:id,name,email', 'channelHead:id,first_name,last_name', 'approvals.actor:id,name,email']);

            $this->logActivity(
                'REJECT_2ND_INTERVIEW',
                'Candidate',
                "Rejected 2nd interview for candidate: {$candidate->full_name}",
                ['candidate_id' => $candidate->id, 'interview_date' => $data['interview_date']],
                'warning'
            );

            return response()->json([
                'status' => 'success',
                'message' => '2nd interview rejected successfully',
                'data' => $candidate,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reject 2nd interview',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Final approval sign-off (candidate officially hired/onboarded).
     */
    public function approveFinal(ApproveFinalRequest $request, string $id)
    {
        try {
            $candidate = Candidate::find($id);

            if (! $candidate) {
                $this->logActivity('APPROVE_FINAL_FAILED', 'Candidate', "Candidate not found with ID: {$id}", ['id' => $id], 'warning');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Candidate not found',
                ], 404);
            }

            $user = auth()->user();
            if (! $candidate->isAccessibleBy($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to candidate record outside your channel or hierarchy',
                ], 403);
            }

            if ($candidate->status !== '2nd_interview_passed') {
                return response()->json([
                    'status' => 'error',
                    'message' => "Candidate final approval requires '2nd_interview_passed' status. Current status: {$candidate->status}",
                ], 422);
            }

            $data = $request->validated();

            InterviewApproval::create([
                'candidate_id'   => $candidate->id,
                'actor_id'       => $user->id,
                'stage'          => 'final_approval',
                'action'         => 'approved',
                'interview_date' => $data['approval_date'],
                'comments'       => $data['comments'],
                'status'         => 'completed',
            ]);

            $candidate->status = 'final_approved';
            $candidate->stage_updated_at = now();
            $candidate->save();

            // Dispatch notification to candidate uploader
            $uploader = User::find($candidate->uploaded_by);
            if ($uploader) {
                $uploader->notify(new CandidateStageStatusNotification($candidate, 'approved', 'final_approval', $user, $data['comments']));
            }

            $candidate->load(['designation', 'uploader:id,name,email', 'channelHead:id,first_name,last_name', 'approvals.actor:id,name,email']);

            $this->logActivity(
                'APPROVE_FINAL',
                'Candidate',
                "Final approval granted for candidate: {$candidate->full_name}",
                ['candidate_id' => $candidate->id, 'approval_date' => $data['approval_date']]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Candidate final approval completed successfully. Candidate officially onboarded!',
                'data' => $candidate,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to perform final approval',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Final rejection (candidate application closed).
     */
    public function rejectFinal(RejectFinalRequest $request, string $id)
    {
        try {
            $candidate = Candidate::find($id);

            if (! $candidate) {
                $this->logActivity('REJECT_FINAL_FAILED', 'Candidate', "Candidate not found with ID: {$id}", ['id' => $id], 'warning');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Candidate not found',
                ], 404);
            }

            $user = auth()->user();
            if (! $candidate->isAccessibleBy($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to candidate record outside your channel or hierarchy',
                ], 403);
            }

            if (in_array($candidate->status, ['final_approved', 'rejected'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Candidate cannot be rejected from final closed status: {$candidate->status}",
                ], 422);
            }

            $data = $request->validated();

            InterviewApproval::create([
                'candidate_id'   => $candidate->id,
                'actor_id'       => $user->id,
                'stage'          => 'final_approval',
                'action'         => 'rejected',
                'interview_date' => $data['rejection_date'],
                'comments'       => $data['comments'],
                'status'         => 'rejected',
            ]);

            $candidate->status = 'rejected';
            $candidate->stage_updated_at = now();
            $candidate->save();

            // Dispatch notification to candidate uploader
            $uploader = User::find($candidate->uploaded_by);
            if ($uploader) {
                $uploader->notify(new CandidateStageStatusNotification($candidate, 'rejected', 'final_approval', $user, $data['comments']));
            }

            $candidate->load(['designation', 'uploader:id,name,email', 'channelHead:id,first_name,last_name', 'approvals.actor:id,name,email']);

            $this->logActivity(
                'REJECT_FINAL',
                'Candidate',
                "Final rejection issued for candidate: {$candidate->full_name}",
                ['candidate_id' => $candidate->id, 'rejection_date' => $data['rejection_date']],
                'warning'
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Candidate final rejection completed successfully',
                'data' => $candidate,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reject candidate',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
