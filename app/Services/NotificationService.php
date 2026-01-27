<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use App\Models\Teacher;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Send notification based on template and trigger event
     */
    public function sendNotification($eventType, $data = [], $recipients = [])
    {
        // Get active templates for this event type
        $templates = NotificationTemplate::active()
            ->forEvent($eventType)
            ->get();

        if ($templates->isEmpty()) {
            Log::info("No active templates found for event type: {$eventType}");
            return false;
        }

        $sentCount = 0;
        foreach ($templates as $template) {
            if ($template->shouldSend($eventType, $data)) {
                $result = $this->sendViaTemplate($template, $data, $recipients);
                if ($result) {
                    $sentCount++;
                }
            }
        }

        return $sentCount > 0;
    }

    /**
     * Send notification using specific template
     */
    public function sendViaTemplate($template, $data = [], $recipients = [])
    {
        try {
            // Determine recipients
            $targetRecipients = $this->resolveRecipients($template, $data, $recipients);
            
            if (empty($targetRecipients)) {
                Log::warning("No recipients found for template: {$template->name}");
                return false;
            }

            // Render template content
            $renderedContent = $template->render($data);
            $subject = $template->subject ? $template->render(['subject' => $template->subject] + $data)['subject'] : 'Notification';

            // Send via appropriate channels
            $results = [];
            foreach ($targetRecipients as $recipient) {
                foreach ($template->channel as $channel) {
                    switch ($channel) {
                        case 'email':
                            $results[] = $this->sendEmail($recipient, $subject, $renderedContent, $data);
                            break;
                        case 'sms':
                            $results[] = $this->sendSMS($recipient, $renderedContent, $data);
                            break;
                        case 'whatsapp':
                            $results[] = $this->sendWhatsApp($recipient, $renderedContent, $data);
                            break;
                        case 'push':
                            $results[] = $this->sendPushNotification($recipient, $subject, $renderedContent, $data);
                            break;
                    }
                }
            }

            return in_array(true, $results);
        } catch (\Exception $e) {
            Log::error("Failed to send notification via template: " . $e->getMessage(), [
                'template_id' => $template->id,
                'event_type' => $template->event_type,
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Send email notification
     */
    public function sendEmail($recipient, $subject, $content, $data = [])
    {
        try {
            Mail::raw($content, function ($message) use ($recipient, $subject, $data) {
                $message->to($recipient['email'])
                        ->subject($subject);
                
                // Add attachments if any
                if (isset($data['attachments']) && is_array($data['attachments'])) {
                    foreach ($data['attachments'] as $attachment) {
                        $message->attach($attachment['path'], [
                            'as' => $attachment['name'] ?? basename($attachment['path']),
                            'mime' => $attachment['mime'] ?? 'application/pdf'
                        ]);
                    }
                }
            });

            Log::info("Email sent successfully", [
                'recipient' => $recipient['email'],
                'subject' => $subject
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send email: " . $e->getMessage(), [
                'recipient' => $recipient['email'],
                'subject' => $subject
            ]);
            return false;
        }
    }

    /**
     * Send SMS notification
     */
    public function sendSMS($recipient, $content, $data = [])
    {
        try {
            // Simulate SMS sending - in production, integrate with actual SMS gateway
            Log::info("SMS sent", [
                'recipient' => $recipient['phone'],
                'content' => $content,
                'gateway' => 'Simulated'
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send SMS: " . $e->getMessage(), [
                'recipient' => $recipient['phone'],
                'content' => $content
            ]);
            return false;
        }
    }

    /**
     * Send WhatsApp notification
     */
    public function sendWhatsApp($recipient, $content, $data = [])
    {
        try {
            // Simulate WhatsApp sending - in production, integrate with WhatsApp Business API
            Log::info("WhatsApp message sent", [
                'recipient' => $recipient['phone'],
                'content' => $content,
                'platform' => 'Simulated'
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp: " . $e->getMessage(), [
                'recipient' => $recipient['phone'],
                'content' => $content
            ]);
            return false;
        }
    }

    /**
     * Send push notification
     */
    public function sendPushNotification($recipient, $title, $content, $data = [])
    {
        try {
            // Simulate push notification - in production, integrate with FCM or OneSignal
            Log::info("Push notification sent", [
                'recipient' => substr($recipient['device_token'] ?? 'N/A', 0, 10) . '...',
                'title' => $title,
                'content' => $content,
                'platform' => 'Simulated'
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send push notification: " . $e->getMessage(), [
                'recipient' => substr($recipient['device_token'] ?? 'N/A', 0, 10) . '...',
                'title' => $title,
                'content' => $content
            ]);
            return false;
        }
    }

    /**
     * Resolve recipients based on template configuration
     */
    private function resolveRecipients($template, $data, $explicitRecipients = [])
    {
        if (!empty($explicitRecipients)) {
            return $explicitRecipients;
        }

        $recipients = [];

        // Get recipients based on roles
        if ($template->recipient_roles) {
            foreach ($template->recipient_roles as $role) {
                switch ($role) {
                    case 'teacher':
                        if (isset($data['teacher_id'])) {
                            $teacher = Teacher::find($data['teacher_id']);
                            if ($teacher && $teacher->email) {
                                $recipients[] = [
                                    'email' => $teacher->email,
                                    'phone' => $teacher->phone ?? '',
                                    'name' => $teacher->name,
                                    'device_token' => $teacher->device_token ?? ''
                                ];
                            }
                        }
                        break;
                        
                    case 'admin':
                        // Get admin users
                        $admins = \App\Models\User::whereHas('roles', function ($q) {
                            $q->where('name', 'admin');
                        })->get();
                        
                        foreach ($admins as $admin) {
                            if ($admin->email) {
                                $recipients[] = [
                                    'email' => $admin->email,
                                    'phone' => $admin->phone ?? '',
                                    'name' => $admin->name,
                                    'device_token' => $admin->device_token ?? ''
                                ];
                            }
                        }
                        break;
                        
                    case 'department_head':
                        if (isset($data['department'])) {
                            // Logic to get department head
                            // This would depend on your department management structure
                        }
                        break;
                }
            }
        }

        return array_unique($recipients, SORT_REGULAR);
    }

    /**
     * Send bulk notifications
     */
    public function sendBulkNotifications($eventType, $recipients, $data = [])
    {
        $sentCount = 0;
        $failedCount = 0;

        foreach ($recipients as $recipient) {
            try {
                $result = $this->sendNotification($eventType, array_merge($data, ['recipient' => $recipient]), [$recipient]);
                if ($result) {
                    $sentCount++;
                } else {
                    $failedCount++;
                }
            } catch (\Exception $e) {
                Log::error("Failed to send bulk notification to recipient: " . $e->getMessage());
                $failedCount++;
            }
        }

        Log::info("Bulk notification results", [
            'event_type' => $eventType,
            'total_recipients' => count($recipients),
            'sent' => $sentCount,
            'failed' => $failedCount
        ]);

        return [
            'sent' => $sentCount,
            'failed' => $failedCount,
            'total' => count($recipients)
        ];
    }



    /**
     * Send notification for late arrival event
     */
    public function sendLateArrivalNotification($teacherId, $lateMinutes)
    {
        $data = [
            'teacher_id' => $teacherId,
            'late_minutes' => $lateMinutes,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s')
        ];

        return $this->sendNotification('teacher_late_arrival', $data);
    }

    /**
     * Send notification for early departure event
     */
    public function sendEarlyDepartureNotification($teacherId, $earlyMinutes)
    {
        $data = [
            'teacher_id' => $teacherId,
            'early_minutes' => $earlyMinutes,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s')
        ];

        return $this->sendNotification('teacher_early_departure', $data);
    }

    /**
     * Send monthly attendance summary
     */
    public function sendMonthlySummary($teacherId, $month, $year)
    {
        $data = [
            'teacher_id' => $teacherId,
            'month' => $month,
            'year' => $year,
            'report_available' => true
        ];

        return $this->sendNotification('monthly_attendance_summary', $data);
    }

    /**
     * Send performance threshold alert
     */
    public function sendPerformanceAlert($teacherId, $metric, $currentValue, $threshold)
    {
        $data = [
            'teacher_id' => $teacherId,
            'metric' => $metric,
            'current_value' => $currentValue,
            'threshold' => $threshold,
            'alert_level' => $currentValue < $threshold ? 'warning' : 'info'
        ];

        return $this->sendNotification('performance_threshold_alert', $data);
    }
}