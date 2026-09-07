<?php

namespace App\Notifications;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CandidateInterviewAssignedNotification extends Notification
{
    use Queueable;

    public Candidate $candidate;
    public string $stage;
    public User $assigner;
    public ?string $assignedDate;
    public ?string $comments;

    /**
     * Create a new notification instance.
     */
    public function __construct(Candidate $candidate, string $stage, User $assigner, ?string $assignedDate = null, ?string $comments = null)
    {
        $this->candidate    = $candidate;
        $this->stage        = $stage;
        $this->assigner     = $assigner;
        $this->assignedDate = $assignedDate;
        $this->comments     = $comments;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $stageLabel = $this->stage === '1st_interview' ? '1st Interview' : '2nd Interview';

        return [
            'type'          => 'INTERVIEW_ASSIGNED',
            'title'         => "Assigned to {$stageLabel}",
            'message'       => "You have been assigned candidate {$this->candidate->first_name} {$this->candidate->last_name} for {$stageLabel} by {$this->assigner->name}.",
            'candidate_id'  => $this->candidate->id,
            'stage'         => $this->stage,
            'assigned_by'   => $this->assigner->name,
            'assigned_date' => $this->assignedDate,
            'comments'      => $this->comments,
        ];
    }
}
