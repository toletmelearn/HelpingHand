<?php

namespace App\Services\Sms;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

class TwilioSmsService
{
    protected $client;
    protected $fromNumber;

    public function __construct()
    {
        $sid = env('TWILIO_SID');
        $token = env('TWILIO_TOKEN');
        $this->fromNumber = env('TWILIO_FROM');

        if ($sid && $token) {
            $this->client = new Client($sid, $token);
        }
    }

    /**
     * Send SMS using Twilio
     */
    public function sendSms($to, $message)
    {
        if (!$this->client) {
            // If Twilio is not configured, log the message or use alternative method
            \Log::warning('Twilio not configured. SMS not sent.', [
                'to' => $to,
                'message' => $message
            ]);
            return false;
        }

        try {
            $response = $this->client->messages->create(
                $to,
                [
                    'from' => $this->fromNumber,
                    'body' => $message
                ]
            );

            return [
                'success' => true,
                'sid' => $response->sid,
                'status' => $response->status
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to send SMS via Twilio: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send low attendance alert SMS
     */
    public function sendLowAttendanceAlert($phoneNumber, $studentName, $attendancePercentage)
    {
        $message = "ALERT: Your child {$studentName} has low attendance of {$attendancePercentage}%. Please ensure regular attendance.";

        return $this->sendSms($phoneNumber, $message);
    }

    /**
     * Send fee payment reminder SMS
     */
    public function sendFeePaymentReminder($phoneNumber, $studentName, $amountDue, $dueDate)
    {
        $message = "REMINDER: Fee payment of Rs.{$amountDue} for {$studentName} is due on {$dueDate}. Please pay on time.";

        return $this->sendSms($phoneNumber, $message);
    }

    /**
     * Send exam schedule SMS
     */
    public function sendExamSchedule($phoneNumber, $studentName, $examTitle, $examDate, $examTime)
    {
        $message = "NOTICE: {$studentName}, {$examTitle} exam is scheduled on {$examDate} at {$examTime}.";

        return $this->sendSms($phoneNumber, $message);
    }

    /**
     * Send result publication SMS
     */
    public function sendResultPublished($phoneNumber, $studentName, $examName, $percentage)
    {
        $message = "RESULT: {$studentName}, your {$examName} result is published. Percentage: {$percentage}%.";

        return $this->sendSms($phoneNumber, $message);
    }
}