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
    protected $alertData;

    /**
     * Create a new notification instance.
     */
    public function __construct($student, $alertData)
    {
        $this->student = $student;
        $this->alertData = $alertData;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Low Attendance Alert - ' . $this->student->name)
            ->line('Your child ' . $this->student->name . ' has low attendance.')
            ->line('Current attendance rate: ' . $this->alertData['attendance_rate'] . '%')
            ->line('Required attendance: 75%')
            ->line('Absent days: ' . $this->alertData['absent_days'])
            ->action('View Attendance Details', url('/parent/attendance/' . $this->student->id))
            ->line('Please ensure your child attends school regularly.')
            ->line('Thank you for your attention to this matter.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'attendance_rate' => $this->alertData['attendance_rate'],
            'absent_days' => $this->alertData['absent_days'],
            'message' => 'Low attendance alert for ' . $this->student->name,
            'type' => 'low_attendance'
        ];
    }
}