<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExamScheduleNotification extends Notification
{
    use Queueable;

    protected $exam;
    protected $student;
    
    /**
     * Create a new notification instance.
     */
    public function __construct($exam, $student)
    {
        $this->exam = $exam;
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
                    ->subject('Upcoming Exam Schedule - ' . $this->exam->title)
                    ->greeting('Hello ' . $notifiable->name)
                    ->line('We are pleased to inform you about the upcoming exam schedule.')
                    ->line('Exam: ' . $this->exam->title)
                    ->line('Subject: ' . $this->exam->subject)
                    ->line('Date: ' . $this->exam->exam_date)
                    ->line('Time: ' . $this->exam->start_time . ' to ' . $this->exam->end_time)
                    ->line('Venue: ' . $this->exam->venue)
                    ->line('Please prepare well for the examination.')
                    ->action('View Exam Details', url('/exams/' . $this->exam->id))
                    ->line('Thank you for your attention.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'exam_id' => $this->exam->id,
            'exam_title' => $this->exam->title,
            'exam_date' => $this->exam->exam_date,
            'start_time' => $this->exam->start_time,
            'end_time' => $this->exam->end_time,
            'student_id' => $this->student->id,
        ];
    }
}