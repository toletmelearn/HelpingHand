<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeePaymentReminder extends Notification
{
    use Queueable;

    protected $fee;
    protected $student;
    
    /**
     * Create a new notification instance.
     */
    public function __construct($fee, $student)
    {
        $this->fee = $fee;
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
                    ->subject('Fee Payment Reminder - ' . $this->student->name)
                    ->greeting('Hello ' . $notifiable->name)
                    ->line('This is a reminder that the fee payment for ' . $this->student->name . ' is due.')
                    ->line('Amount Due: ₹' . $this->fee->amount_due)
                    ->line('Due Date: ' . $this->fee->due_date)
                    ->action('Pay Now', url('/fees/payment'))
                    ->line('Please make the payment at your earliest convenience.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'fee_id' => $this->fee->id,
            'student_name' => $this->student->name,
            'amount_due' => $this->fee->amount_due,
            'due_date' => $this->fee->due_date,
        ];
    }
}