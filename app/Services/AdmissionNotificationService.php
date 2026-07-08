<?php

namespace App\Services;

use App\Models\AdmissionEnquiry;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;

class AdmissionNotificationService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Notify the parent/guardian on an admission enquiry about a stage change,
     * if an enabled NotificationSetting exists for the given event type.
     */
    public function notifyStage(AdmissionEnquiry $enquiry, string $eventType, array $extra = []): ?NotificationLog
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('notification_settings') || !\Illuminate\Support\Facades\Schema::hasTable('notification_logs')) {
            return null;
        }

        $setting = NotificationSetting::where('event_type', $eventType)
            ->where('is_enabled', true)
            ->first();

        if (!$setting) {
            return null;
        }

        $data = array_merge([
            'candidate_name' => $enquiry->candidate_name,
            'parent_name' => $enquiry->parent_name,
        ], $extra);

        $subject = $this->renderTemplate($setting->template_subject, $data);
        $message = $this->renderTemplate($setting->template_body, $data);

        $log = NotificationLog::create([
            'notification_setting_id' => $setting->id,
            'recipient_type' => 'parent',
            'recipient_id' => $enquiry->id,
            'notification_type' => $setting->notification_type,
            'subject' => $subject,
            'message' => $message,
            'status' => 'pending',
        ]);

        try {
            $sent = false;

            if (in_array($setting->notification_type, ['sms', 'both']) && $enquiry->phone) {
                $sent = $this->notificationService->sendSms($enquiry->phone, $message) || $sent;
            }

            if (in_array($setting->notification_type, ['email', 'both']) && $enquiry->email) {
                $sent = $this->notificationService->sendEmail($enquiry->email, $subject ?? 'School Notification', $message) || $sent;
            }

            $log->update([
                'status' => $sent ? 'sent' : 'failed',
                'sent_at' => $sent ? now() : null,
                'failed_reason' => $sent ? null : 'No deliverable contact channel available for the configured notification type.',
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'failed_reason' => $e->getMessage(),
                'retry_count' => $log->retry_count + 1,
            ]);
        }

        return $log;
    }

    private function renderTemplate(?string $template, array $data): ?string
    {
        if (!$template) {
            return $template;
        }

        foreach ($data as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }

        return $template;
    }
}
