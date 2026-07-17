<?php

namespace App\Notifications;

use App\Models\Student;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fires when a Class Teacher or Receptionist (communicate-defaulters only,
 * not the broader manage-defaulters) dispatches a defaulter communication --
 * gives Admin/Principal visibility into what staff are doing on their
 * behalf, since neither role can promote/override a stage themselves.
 */
class DefaulterActionNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Student $student,
        protected User $actor,
        protected string $actorRoleLabel,
        protected string $channel,
        protected string $stage
    ) {
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $title = 'Defaulter communication sent';
        $message = "{$this->actor->name} ({$this->actorRoleLabel}) sent a " . ucfirst($this->channel)
            . " to {$this->student->name} (Stage: {$this->stage}).";

        return [
            'title' => $title,
            'message' => $message,
            'student_id' => $this->student->id,
            'actor_id' => $this->actor->id,
            'actor_role' => $this->actorRoleLabel,
            'channel' => $this->channel,
            'stage' => $this->stage,
        ];
    }
}
