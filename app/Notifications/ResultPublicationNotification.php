<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResultPublicationNotification extends Notification
{
    use Queueable;

    protected $result;
    protected $student;
    
    /**
     * Create a new notification instance.
     */
    public function __construct($result, $student)
    {
        $this->result = $result;
        $this->student = $student;
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
                    ->subject('Result Published - ' . $this->student->name)
                    ->greeting('Hello ' . $notifiable->name)
                    ->line('Great news! The results for ' . $this->student->name . ' have been published.')
                    ->line('Exam: ' . $this->result->exam_name)
                    ->line('Marks Obtained: ' . $this->result->marks_obtained . '/' . $this->result->total_marks)
                    ->line('Grade: ' . $this->result->grade)
                    ->line('Percentage: ' . $this->result->percentage . '%')
                    ->action('View Detailed Result', url('/results/' . $this->result->id))
                    ->line('Congratulations on the achievement!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'result_id' => $this->result->id,
            'student_name' => $this->student->name,
            'exam_name' => $this->result->exam_name,
            'marks_obtained' => $this->result->marks_obtained,
            'total_marks' => $this->result->total_marks,
            'grade' => $this->result->grade,
            'percentage' => $this->result->percentage,
        ];
    }
}