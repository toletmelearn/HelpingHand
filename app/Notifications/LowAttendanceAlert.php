<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowAttendanceAlert extends Notification
{
    use Queueable;

    protected $student;
    protected $attendancePercentage;
    
    /**
     * Create a new notification instance.
     */
    public function __construct($student, $attendancePercentage)
    {
        $this->student = $student;
        $this->attendancePercentage = $attendancePercentage;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Low Attendance Alert - ' . $this->student->name)
                    ->greeting('Dear ' . $notifiable->name)
                    ->line('We are concerned to inform you that your ward, ' . $this->student->name . ', has low attendance.')
                    ->line('Current Attendance: ' . $this->attendancePercentage . '%')
                    ->line('Minimum required attendance is typically 75%.')
                    ->line('Please ensure regular attendance to maintain academic progress.')
                    ->action('View Attendance Details', url('/students/' . $this->student->id . '/attendance'))
                    ->line('Thank you for your attention to this matter.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'attendance_percentage' => $this->attendancePercentage,
        ];
    }
}