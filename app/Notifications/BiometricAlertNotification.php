<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\TeacherBiometricRecord;

class BiometricAlertNotification extends Notification
{
    use Queueable;

    protected $record;
    protected $type;
    protected $message;

    /**
     * Create a new notification instance.
     *
     * @param TeacherBiometricRecord $record
     * @param string $type
     * @param string $message
     */
    public function __construct(TeacherBiometricRecord $record, $type, $message)
    {
        $this->record = $record;
        $this->type = $type;
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database']; // We'll use database notifications for this
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->line($this->message)
                    ->action('View Details', url('/teacher/biometric/dashboard'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'record_id' => $this->record->id,
            'teacher_id' => $this->record->teacher_id,
            'date' => $this->record->date,
            'type' => $this->type,
            'message' => $this->message,
            'data' => [
                'first_in_time' => $this->record->first_in_time,
                'last_out_time' => $this->record->last_out_time,
                'late_minutes' => $this->record->late_minutes,
                'early_departure_minutes' => $this->record->early_departure_minutes,
                'arrival_status' => $this->record->arrival_status,
                'departure_status' => $this->record->departure_status,
            ]
        ];
    }
}