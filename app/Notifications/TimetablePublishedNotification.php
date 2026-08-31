<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Phase 2.6: nothing notified a teacher when their published schedule
 * changed -- database channel only (matches the existing admin
 * notification bell in layouts/admin.blade.php, which just reads
 * Auth::user()->unreadNotifications; no new UI needed there). TeacherLogin
 * gained the Notifiable trait specifically to receive this.
 */
class TimetablePublishedNotification extends Notification
{
    use Queueable;

    /** @param string[] $classNames distinct classes this teacher is affected by */
    public function __construct(
        private array $classNames,
        private int $periodCount,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $classList = implode(', ', $this->classNames);

        return [
            'title' => 'Timetable Updated',
            'message' => "The published timetable for {$classList} has changed ({$this->periodCount} period(s) affecting you) -- check your schedule.",
            'class_names' => $this->classNames,
            'period_count' => $this->periodCount,
        ];
    }
}
