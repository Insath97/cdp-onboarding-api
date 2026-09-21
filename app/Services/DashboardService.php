<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\User;
use App\Traits\ActivityLogTrait;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    use ActivityLogTrait;

    /**
     * Retrieve all aggregated dashboard metrics for the given user.
     *
     * @param User $user
     * @return array
     */
    public function getMetrics(User $user): array
    {
        // 1. Grouped query to retrieve counts by status scoped to user's hierarchy
        $statusCounts = Candidate::accessibleBy($user)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->toArray();

        // 2. Compute Top KPI Cards
        $totalCandidates = array_sum($statusCounts);
        $inProgress = (int) (
            ($statusCounts['pending'] ?? 0) +
            ($statusCounts['1st_interview'] ?? 0) +
            ($statusCounts['1st_interview_passed'] ?? 0) +
            ($statusCounts['2nd_interview'] ?? 0) +
            ($statusCounts['2nd_interview_passed'] ?? 0)
        );
        $hired = (int) ($statusCounts['final_approved'] ?? 0);
        $rejected = (int) (
            ($statusCounts['1st_interview_rejected'] ?? 0) +
            ($statusCounts['2nd_interview_rejected'] ?? 0) +
            ($statusCounts['rejected'] ?? 0)
        );

        // 3. Compute Hiring Pipeline Funnel
        $pipeline = [
            'cv_submitted'     => (int) ($statusCounts['pending'] ?? 0),
            'first_interview'  => (int) (($statusCounts['1st_interview'] ?? 0) + ($statusCounts['1st_interview_passed'] ?? 0)),
            'second_interview' => (int) (($statusCounts['2nd_interview'] ?? 0) + ($statusCounts['2nd_interview_passed'] ?? 0)),
            'hired'            => $hired,
        ];

        // 4. Retrieve Recent Candidates (latest 10)
        $recentCandidates = Candidate::accessibleBy($user)
            ->with([
                'designation:id,name,code',
                'uploader:id,name,email',
                'channelHead:id,first_name,last_name',
            ])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function (Candidate $candidate) {
                $initials = '';
                if ($candidate->first_name) {
                    $initials .= strtoupper(substr($candidate->first_name, 0, 1));
                }
                if ($candidate->last_name) {
                    $initials .= strtoupper(substr($candidate->last_name, 0, 1));
                }

                return [
                    'id'            => $candidate->id,
                    'first_name'    => $candidate->first_name,
                    'last_name'     => $candidate->last_name,
                    'full_name'     => $candidate->full_name,
                    'initials'      => $initials ?: 'NA',
                    'nic_number'    => $candidate->nic_number,
                    'phone_number'  => $candidate->phone_number,
                    'status'        => $candidate->status,
                    'stage_label'   => $this->formatStageLabel($candidate->status),
                    'designation'   => $candidate->designation ? [
                        'id'   => $candidate->designation->id,
                        'name' => $candidate->designation->name,
                        'code' => $candidate->designation->code,
                    ] : null,
                    'uploader'      => $candidate->uploader ? [
                        'id'    => $candidate->uploader->id,
                        'name'  => $candidate->uploader->name,
                        'email' => $candidate->uploader->email,
                    ] : null,
                    'submitted_at'  => $candidate->submitted_at?->toISOString() ?? $candidate->created_at->toISOString(),
                    'submitted_ago' => $candidate->created_at->diffForHumans(),
                    'cv_url'        => $candidate->cv_url,
                ];
            })
            ->values()
            ->toArray();

        return [
            'stats' => [
                'total_candidates' => $totalCandidates,
                'in_progress'      => $inProgress,
                'hired'            => $hired,
                'rejected'         => $rejected,
            ],
            'pipeline' => $pipeline,
            'recent_candidates' => $recentCandidates,
        ];
    }

    /**
     * Convert technical database status to a human-readable UI stage label.
     *
     * @param string $status
     * @return string
     */
    protected function formatStageLabel(string $status): string
    {
        return match ($status) {
            'pending'                => 'CV Submitted',
            '1st_interview'          => '1st Interview',
            '1st_interview_passed'   => '1st Interview Passed',
            '1st_interview_rejected' => '1st Interview Rejected',
            '2nd_interview'          => '2nd Interview',
            '2nd_interview_passed'   => '2nd Interview Passed',
            '2nd_interview_rejected' => '2nd Interview Rejected',
            'final_approved'         => 'Hired',
            'rejected'               => 'Rejected',
            default                  => ucwords(str_replace('_', ' ', $status)),
        };
    }
}
