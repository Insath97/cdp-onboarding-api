<?php

namespace App\Models;

use App\Enums\CandidateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Candidate extends Model
{
    public const SOURCE_DIRECT = 'direct';
    public const SOURCE_REFERRAL = 'referral';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'nic_number',
        'phone_number',
        'secondary_number',
        'designation_id',
        'expected_salary',
        'cv_path',
        'status',
        'source',
        'uploaded_by',
        'uploader_comments',
        'submitted_at',
        'channel_head_employee_id',
        'stage_updated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_salary'  => 'decimal:2',
            'submitted_at'     => 'datetime',
            'stage_updated_at' => 'datetime',
            'status'           => CandidateStatus::class,
        ];
    }

    /**
     * Appends custom accessors to array/JSON representation.
     */
    protected $appends = ['full_name', 'cv_url'];

    /**
     * Get full name accessor.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get accessible public URL for the uploaded CV.
     */
    public function getCvUrlAttribute(): ?string
    {
        if (! $this->cv_path) {
            return null;
        }

        return Storage::disk('private')->temporaryUrl($this->cv_path, now()->addMinutes(30));
    }

    /**
     * Get the designation the candidate applied for.
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Get the staff user who uploaded/submitted the CV.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the snapshotted Channel Head employee at submission time.
     */
    public function channelHead(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'channel_head_employee_id');
    }

    /**
     * Get all interview approval and workflow tracking records for this candidate.
     */
    public function approvals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InterviewApproval::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get the latest 2nd interview assignment approval record.
     */
    public function latest2ndInterviewAssignment(): ?InterviewApproval
    {
        return InterviewApproval::where('candidate_id', $this->id)
            ->where('stage', '2nd_interview')
            ->where('action', 'assigned')
            ->latest('id')
            ->first();
    }

    /**
     * Check if the given user is the actively assigned interviewer for 2nd interview.
     */
    public function isAssigned2ndInterviewer(User $user): bool
    {
        $assignment = $this->latest2ndInterviewAssignment();

        return $assignment && (int) $assignment->assigned_to_user_id === (int) $user->id;
    }

    /**
     * Scope a query to search across first_name, last_name, nic_number, and phone_number.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%")
              ->orWhere('nic_number', 'like', "%{$search}%")
              ->orWhere('phone_number', 'like', "%{$search}%");
        });
    }

    /**
     * Scope a query to filter by candidate status.
     * Supports single status, array of statuses, comma-separated string, or CandidateStatus enum.
     */
    public function scopeStatus($query, $status)
    {
        if ($status instanceof CandidateStatus) {
            return $query->where('status', $status->value);
        }

        if (is_array($status)) {
            $statuses = array_map(fn ($s) => $s instanceof CandidateStatus ? $s->value : $s, $status);
            return $query->whereIn('status', $statuses);
        }

        if (is_string($status) && str_contains($status, ',')) {
            $statuses = array_filter(array_map('trim', explode(',', $status)));
            return $query->whereIn('status', $statuses);
        }

        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by workflow stage.
     */
    public function scopeStage($query, string $stage)
    {
        $statuses = CandidateStatus::forStage($stage);

        return ! empty($statuses) ? $query->whereIn('status', $statuses) : $query;
    }

    /**
     * Scope a query to filter by candidate CV submission source.
     */
    public function scopeSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope query to only include candidates accessible by the given user:
     * - Super Admins see all candidates across the organization.
     * - Users with 'Candidate Assign 2nd Interview' see the 2nd interview pool to assign interviewers (cross-channel).
     * - Users with 'Candidate 2nd Interview List' only see candidates in 2nd interview stage statuses (cross-channel).
     * - Users with Final Approval permissions see candidates who passed 2nd interview (cross-channel).
     * - Otherwise scoped to uploader hierarchy, Channel Head snapshot, or direct interview assignments.
     */
    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->hasRole('Super Admin')) {
            return $query;
        }

        $employeeId = $user->employee_id;
        $descendantUserIds = $user->getAllDescendantIds();
        $allowedUserIds = array_unique(array_merge([$user->id], $descendantUserIds));

        // Users with 'Candidate Assign 2nd Interview' manage the 2nd interview pool (assign interviewers)
        $canAccess2ndInterview = $user->hasAnyPermission([
            'Candidate Assign 2nd Interview',
        ]);

        // View-only users for the 2nd interview list (no action rights)
        $canView2ndList = $user->hasAnyPermission([
            'Candidate 2nd Interview List',
        ]);

        $canAccessFinalApproval = $user->hasAnyPermission([
            'Candidate Approve Final',
            'Candidate Reject Final',
        ]);

        $secondInterviewStatuses = CandidateStatus::secondInterviewPool();
        $finalApprovalStatuses = CandidateStatus::finalApprovalPool();
        $secondInterviewStageStatuses = CandidateStatus::secondInterviewStageStatuses();

        return $query->where(function ($q) use (
            $user,
            $employeeId,
            $allowedUserIds,
            $canAccess2ndInterview,
            $canView2ndList,
            $canAccessFinalApproval,
            $secondInterviewStatuses,
            $finalApprovalStatuses,
            $secondInterviewStageStatuses
        ) {
            // 1. Uploaded by self or any subordinate in reporting manager hierarchy
            $q->whereIn('uploaded_by', $allowedUserIds);

            // 2. Channel Head for candidate's channel
            if ($employeeId) {
                $q->orWhere('channel_head_employee_id', $employeeId);
            }

            // 3. Assigned to user in workflow approvals
            $q->orWhereHas('approvals', function ($approvalQuery) use ($user) {
                $approvalQuery->where('assigned_to_user_id', $user->id);
            });

            // 4. Cross-channel access for candidates who completed 1st interview
            if ($canAccess2ndInterview) {
                $q->orWhereIn('status', $secondInterviewStatuses);
            }

            // 5. View-only access to the 2nd interview list (stage 2 statuses)
            if ($canView2ndList) {
                $q->orWhereIn('status', $secondInterviewStageStatuses);
            }

            // 6. Cross-channel access for candidates who completed 2nd interview
            if ($canAccessFinalApproval) {
                $q->orWhereIn('status', $finalApprovalStatuses);
            }
        });
    }

    /**
     * Check if a single candidate instance is accessible by the given user.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        $employeeId = $user->employee_id;
        $descendantUserIds = $user->getAllDescendantIds();
        $allowedUserIds = array_unique(array_merge([$user->id], $descendantUserIds));

        // 1. Uploaded by self or subordinate in hierarchy
        if (in_array($this->uploaded_by, $allowedUserIds)) {
            return true;
        }

        // 2. Channel Head of candidate's channel
        if ($employeeId && $this->channel_head_employee_id == $employeeId) {
            return true;
        }

        // 3. Assigned to user in workflow approvals
        if ($this->approvals()->where('assigned_to_user_id', $user->id)->exists()) {
            return true;
        }

        $statusValue = $this->status instanceof CandidateStatus ? $this->status->value : (string) $this->status;

        // 4. Cross-channel access for assigners of the 2nd interview pool
        if (in_array($statusValue, CandidateStatus::secondInterviewPool()) && $user->hasAnyPermission([
            'Candidate Assign 2nd Interview',
        ])) {
            return true;
        }

        // 5. View-only access to the 2nd interview list (stage 2 statuses)
        if (in_array($statusValue, CandidateStatus::secondInterviewStageStatuses()) && $user->hasAnyPermission([
            'Candidate 2nd Interview List',
        ])) {
            return true;
        }

        // 6. Cross-channel access for candidates who completed 2nd interview
        if (in_array($statusValue, CandidateStatus::finalApprovalPool()) && $user->hasAnyPermission([
            'Candidate Approve Final',
            'Candidate Reject Final',
        ])) {
            return true;
        }

        return false;
    }
}
