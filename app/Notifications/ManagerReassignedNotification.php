<?php

namespace App\Notifications;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ManagerReassignedNotification extends Notification
{
    use Queueable;

    public Employee $employee;
    public ?Employee $newManager;
    public string $effectiveFrom;
    public User $actor;

    /**
     * Create a new notification instance.
     */
    public function __construct(Employee $employee, ?Employee $newManager, string $effectiveFrom, User $actor)
    {
        $this->employee      = $employee;
        $this->newManager    = $newManager;
        $this->effectiveFrom = $effectiveFrom;
        $this->actor         = $actor;
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
        $managerName = $this->newManager ? $this->newManager->full_name : 'None (Top level)';

        return [
            'type'           => 'MANAGER_REASSIGNED',
            'title'          => 'Reporting Manager Reassigned',
            'message'        => "Reporting manager for {$this->employee->full_name} has been set to {$managerName} effective from {$this->effectiveFrom}.",
            'employee_id'    => $this->employee->id,
            'new_manager_id' => $this->newManager?->id,
            'manager_name'   => $managerName,
            'effective_from' => $this->effectiveFrom,
            'changed_by'     => $this->actor->name,
        ];
    }
}
