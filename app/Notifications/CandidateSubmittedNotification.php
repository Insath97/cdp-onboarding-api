<?php

namespace App\Notifications;

use App\Models\Candidate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CandidateSubmittedNotification extends Notification
{
    use Queueable;

    public Candidate $candidate;

    /**
     * Create a new notification instance.
     */
    public function __construct(Candidate $candidate)
    {
        $this->candidate = $candidate;
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
        return [
            'type'         => 'CANDIDATE_SUBMITTED',
            'title'        => 'New Candidate CV Submitted',
            'message'      => "A new candidate {$this->candidate->first_name} {$this->candidate->last_name} was submitted for designation #{$this->candidate->designation_id}.",
            'candidate_id' => $this->candidate->id,
            'first_name'   => $this->candidate->first_name,
            'last_name'    => $this->candidate->last_name,
            'status'       => $this->candidate->status,
            'uploaded_by'  => $this->candidate->uploaded_by,
            'submitted_at' => $this->candidate->submitted_at?->toIso8601String(),
        ];
    }
}
