<?php

namespace App\Notifications;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CandidateStageStatusNotification extends Notification
{
    use Queueable;

    public Candidate $candidate;
    public string $action; // approved, rejected
    public string $stage;  // 1st_interview, 2nd_interview, final_approval
    public User $actor;
    public ?string $comments;

    /**
     * Create a new notification instance.
     */
    public function __construct(Candidate $candidate, string $action, string $stage, User $actor, ?string $comments = null)
    {
        $this->candidate = $candidate;
        $this->action    = $action;
        $this->stage     = $stage;
        $this->actor     = $actor;
        $this->comments  = $comments;
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
        $statusWord = $this->action === 'approved' ? 'passed' : 'rejected';
        $title = "Candidate {$this->candidate->first_name} {$this->candidate->last_name} {$statusWord}";

        return [
            'type'         => 'CANDIDATE_STATUS_CHANGED',
            'title'        => $title,
            'message'      => "Candidate {$this->candidate->first_name} {$this->candidate->last_name} was {$statusWord} at stage '{$this->stage}' by {$this->actor->name}.",
            'candidate_id' => $this->candidate->id,
            'action'       => $this->action,
            'stage'        => $this->stage,
            'new_status'   => $this->candidate->status,
            'actor_name'   => $this->actor->name,
            'comments'     => $this->comments,
        ];
    }
}
