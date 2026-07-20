<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceMarked extends Notification
{
    use Queueable;

    protected $student;
    protected $date;
    protected $status;

    /**
     * Create a new notification instance.
     */
    public function __construct($student, $date, $status)
    {
        $this->student = $student;
        $this->date = $date;
        $this->status = $status;
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
        $statusText = ucfirst($this->status);
        $dateText = date('F j, Y', strtotime($this->date));
        
        return (new MailMessage)
            ->subject($statusText . ' - ' . $this->student->name . ' - ' . $dateText)
            ->line('Attendance has been marked for your child ' . $this->student->name)
            ->line('Date: ' . $dateText)
            ->line('Status: ' . $statusText)
            ->action('View Attendance Details', url('/parent/attendance/' . $this->student->id))
            ->line('Thank you for your continued support.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'date' => $this->date,
            'status' => $this->status,
            'message' => 'Attendance marked as ' . $this->status . ' for ' . $this->student->name,
            'type' => 'attendance_marked'
        ];
    }
}