<?php

namespace App\Enums;

enum CandidateStatus: string
{
    case PENDING = 'pending';
    case DOCUMENT_SUBMITTED = 'document_submitted';
    case DOCUMENT_VERIFIED = 'document_verified';
    case DOCUMENT_REJECTED = 'document_rejected';
    case FIRST_INTERVIEW = '1st_interview';
    case FIRST_INTERVIEW_PASSED = '1st_interview_passed';
    case FIRST_INTERVIEW_REJECTED = '1st_interview_rejected';
    case SECOND_INTERVIEW = '2nd_interview';
    case SECOND_INTERVIEW_PASSED = '2nd_interview_passed';
    case SECOND_INTERVIEW_REJECTED = '2nd_interview_rejected';
    case FINAL_APPROVED = 'final_approved';
    case REJECTED = 'rejected';

    /**
     * Get human-readable UI label.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'CV Submitted',
            self::DOCUMENT_SUBMITTED => 'Documents Submitted',
            self::DOCUMENT_VERIFIED => 'Documents Verified',
            self::DOCUMENT_REJECTED => 'Documents Rejected',
            self::FIRST_INTERVIEW => '1st Interview',
            self::FIRST_INTERVIEW_PASSED => '1st Interview Passed',
            self::FIRST_INTERVIEW_REJECTED => '1st Interview Rejected',
            self::SECOND_INTERVIEW => '2nd Interview',
            self::SECOND_INTERVIEW_PASSED => '2nd Interview Passed',
            self::SECOND_INTERVIEW_REJECTED => '2nd Interview Rejected',
            self::FINAL_APPROVED => 'Hired',
            self::REJECTED => 'Rejected',
        };
    }

    /**
     * Get the workflow stage this status belongs to.
     */
    public function stage(): string
    {
        return match ($this) {
            self::PENDING,
            self::DOCUMENT_SUBMITTED,
            self::DOCUMENT_VERIFIED,
            self::DOCUMENT_REJECTED,
            self::FIRST_INTERVIEW,
            self::FIRST_INTERVIEW_REJECTED => '1st_interview',

            self::FIRST_INTERVIEW_PASSED,
            self::SECOND_INTERVIEW,
            self::SECOND_INTERVIEW_REJECTED => '2nd_interview',

            self::SECOND_INTERVIEW_PASSED,
            self::FINAL_APPROVED,
            self::REJECTED => 'final_approval',
        };
    }

    /**
     * Get array of all string status values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Statuses associated with a given workflow stage.
     *
     * @param string $stage
     * @return array<string>
     */
    public static function forStage(string $stage): array
    {
        return match ($stage) {
            '1st_interview' => [
                self::PENDING->value,
                self::DOCUMENT_SUBMITTED->value,
                self::DOCUMENT_VERIFIED->value,
                self::DOCUMENT_REJECTED->value,
                self::FIRST_INTERVIEW->value,
                self::FIRST_INTERVIEW_REJECTED->value,
            ],
            '2nd_interview' => [
                self::FIRST_INTERVIEW_PASSED->value,
                self::SECOND_INTERVIEW->value,
                self::SECOND_INTERVIEW_REJECTED->value,
            ],
            'final_approval' => [
                self::SECOND_INTERVIEW_PASSED->value,
                self::FINAL_APPROVED->value,
                self::REJECTED->value,
            ],
            default => [],
        };
    }

    /**
     * Statuses accessible to users with 1st interview management permissions.
     * Stages where the assigner reviews documents and assigns the interview.
     * 'pending' is excluded — it stays with the uploader's own hierarchy.
     *
     * @return array<string>
     */
    public static function firstInterviewPool(): array
    {
        return [
            self::DOCUMENT_SUBMITTED->value,
            self::DOCUMENT_VERIFIED->value,
            self::DOCUMENT_REJECTED->value,
            self::FIRST_INTERVIEW->value,
        ];
    }

    /**
     * Statuses accessible to users with 2nd interview management permissions.
     *
     * @return array<string>
     */
    public static function secondInterviewPool(): array
    {
        return [
            self::FIRST_INTERVIEW_PASSED->value,
            self::SECOND_INTERVIEW->value,
            self::SECOND_INTERVIEW_PASSED->value,
            self::SECOND_INTERVIEW_REJECTED->value,
            self::FINAL_APPROVED->value,
            self::REJECTED->value,
        ];
    }

    /**
     * Statuses visible to view-only 2nd interview list users.
     *
     * @return array<string>
     */
    public static function secondInterviewStageStatuses(): array
    {
        return [
            self::FIRST_INTERVIEW_PASSED->value,
            self::SECOND_INTERVIEW->value,
            self::SECOND_INTERVIEW_REJECTED->value,
        ];
    }

    /**
     * Determine if this status represents an in-progress / active application.
     */
    public function isInProgress(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::DOCUMENT_SUBMITTED,
            self::DOCUMENT_VERIFIED,
            self::DOCUMENT_REJECTED,
            self::FIRST_INTERVIEW,
            self::FIRST_INTERVIEW_PASSED,
            self::SECOND_INTERVIEW,
            self::SECOND_INTERVIEW_PASSED,
        ], true);
    }

    /**
     * Determine if this status is terminal (rejection or final approved).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::FIRST_INTERVIEW_REJECTED,
            self::SECOND_INTERVIEW_REJECTED,
            self::FINAL_APPROVED,
            self::REJECTED,
        ], true);
    }

    /**
     * Get array of status string values that are in-progress.
     *
     * @return array<string>
     */
    public static function inProgressStatuses(): array
    {
        return [
            self::PENDING->value,
            self::DOCUMENT_SUBMITTED->value,
            self::DOCUMENT_VERIFIED->value,
            self::DOCUMENT_REJECTED->value,
            self::FIRST_INTERVIEW->value,
            self::FIRST_INTERVIEW_PASSED->value,
            self::SECOND_INTERVIEW->value,
            self::SECOND_INTERVIEW_PASSED->value,
        ];
    }

    /**
     * Get array of status string values that are terminal.
     *
     * @return array<string>
     */
    public static function terminalStatuses(): array
    {
        return [
            self::FIRST_INTERVIEW_REJECTED->value,
            self::SECOND_INTERVIEW_REJECTED->value,
            self::FINAL_APPROVED->value,
            self::REJECTED->value,
        ];
    }

    /**
     * Statuses accessible to users with Final Approval permissions.
     *
     * @return array<string>
     */
    public static function finalApprovalPool(): array
    {
        return [
            self::SECOND_INTERVIEW_PASSED->value,
            self::FINAL_APPROVED->value,
            self::REJECTED->value,
        ];
    }
}
