<?php

namespace App\Services;

use App\Services\Sms\TwilioSmsService;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $smsService;

    public function __construct($smsService = null)
    {
        $this->smsService = $smsService ?? new TwilioSmsService();
    }

    /**
     * Sends via Twilio when TWILIO_SID/TWILIO_TOKEN/TWILIO_FROM are set in
     * .env; TwilioSmsService itself no-ops (logs + returns false) when they
     * aren't, so this is safe to call whether or not SMS is configured.
     */
    public function sendSms($to, string $message): bool
    {
        if (is_array($to)) {
            $phone = $to['phone'] ?? $to['mobile'] ?? null;
            if (!$phone) {
                return false;
            }
            $to = $phone;
        }

        $result = $this->smsService->send($to, $message);
        return $result['success'] ?? false;
    }

    public function sendWhatsApp(string $to, string $message): bool
    {
        Log::info("Mock WhatsApp sent to {$to}: {$message}");
        return true;
    }

    public function sendEmail($recipient, string $subject, string $content): bool
    {
        $email = is_array($recipient) ? ($recipient['email'] ?? null) : $recipient;
        Log::info("Mock Email sent to {$email}: {$subject}");
        return true;
    }

    public function queueNotification($recipient, string $type, string $subject, string $content): bool
    {
        Log::info("Mock Notification queued for " . (is_array($recipient) ? json_encode($recipient) : $recipient) . " type: {$type}");
        return true;
    }

    public function sendLateArrivalNotification($teacherId, $lateMinutes): bool
    {
        Log::info("Mock Late Arrival Notification sent for teacher {$teacherId}: {$lateMinutes} minutes late");
        return true;
    }

    public function sendEarlyDepartureNotification($teacherId, $earlyMinutes): bool
    {
        Log::info("Mock Early Departure Notification sent for teacher {$teacherId}: {$earlyMinutes} minutes early");
        return true;
    }

    public function sendMonthlySummary($teacherId, $month, $year): bool
    {
        Log::info("Mock Monthly Summary sent for teacher {$teacherId} for {$year}-{$month}");
        return true;
    }
}