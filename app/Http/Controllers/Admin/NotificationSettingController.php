<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationSetting;
use App\Models\NotificationLog;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationSettingController extends Controller
{
    public function index(Request $request)
    {
        $settings = NotificationSetting::with('creator')
            ->when($request->event_type, function ($query) use ($request) {
                return $query->where('event_type', $request->event_type);
            })
            ->when($request->notification_type, function ($query) use ($request) {
                return $query->where('notification_type', $request->notification_type);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $eventTypes = NotificationSetting::EVENT_TYPES;
        $notificationTypes = NotificationSetting::NOTIFICATION_TYPES;

        return view('admin.notifications.settings.index', compact('settings', 'eventTypes', 'notificationTypes'));
    }

    public function create()
    {
        $eventTypes = NotificationSetting::EVENT_TYPES;
        $notificationTypes = NotificationSetting::NOTIFICATION_TYPES;
        $scheduleTypes = NotificationSetting::SCHEDULE_TYPES;
        $recipientTypes = NotificationLog::RECIPIENT_TYPES;
        
        return view('admin.notifications.settings.create', compact(
            'eventTypes', 'notificationTypes', 'scheduleTypes', 'recipientTypes'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'event_type' => 'required|string|in:' . implode(',', array_keys(NotificationSetting::EVENT_TYPES)),
            'notification_type' => 'required|string|in:' . implode(',', array_keys(NotificationSetting::NOTIFICATION_TYPES)),
            'template_subject' => 'nullable|string|max:255',
            'template_body' => 'required|string',
            'recipients' => 'required|array',
            'recipients.*' => 'string|in:' . implode(',', array_keys(NotificationLog::RECIPIENT_TYPES)),
            'schedule_type' => 'required|string|in:' . implode(',', array_keys(NotificationSetting::SCHEDULE_TYPES)),
            'is_enabled' => 'boolean',
        ]);

        $setting = NotificationSetting::create([
            'event_type' => $request->event_type,
            'notification_type' => $request->notification_type,
            'template_subject' => $request->template_subject,
            'template_body' => $request->template_body,
            'recipients' => $request->recipients,
            'schedule_type' => $request->schedule_type,
            'is_enabled' => $request->is_enabled ?? true,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.notification-settings.index')
            ->with('success', 'Notification setting created successfully.');
    }

    public function show(NotificationSetting $notificationSetting)
    {
        $logs = $notificationSetting->logs()
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return view('admin.notifications.settings.show', compact('notificationSetting', 'logs'));
    }

    public function edit(NotificationSetting $notificationSetting)
    {
        $eventTypes = NotificationSetting::EVENT_TYPES;
        $notificationTypes = NotificationSetting::NOTIFICATION_TYPES;
        $scheduleTypes = NotificationSetting::SCHEDULE_TYPES;
        $recipientTypes = NotificationLog::RECIPIENT_TYPES;
        
        return view('admin.notifications.settings.edit', compact(
            'notificationSetting', 'eventTypes', 'notificationTypes', 'scheduleTypes', 'recipientTypes'
        ));
    }

    public function update(Request $request, NotificationSetting $notificationSetting)
    {
        $request->validate([
            'event_type' => 'required|string|in:' . implode(',', array_keys(NotificationSetting::EVENT_TYPES)),
            'notification_type' => 'required|string|in:' . implode(',', array_keys(NotificationSetting::NOTIFICATION_TYPES)),
            'template_subject' => 'nullable|string|max:255',
            'template_body' => 'required|string',
            'recipients' => 'required|array',
            'recipients.*' => 'string|in:' . implode(',', array_keys(NotificationLog::RECIPIENT_TYPES)),
            'schedule_type' => 'required|string|in:' . implode(',', array_keys(NotificationSetting::SCHEDULE_TYPES)),
            'is_enabled' => 'boolean',
        ]);

        $notificationSetting->update([
            'event_type' => $request->event_type,
            'notification_type' => $request->notification_type,
            'template_subject' => $request->template_subject,
            'template_body' => $request->template_body,
            'recipients' => $request->recipients,
            'schedule_type' => $request->schedule_type,
            'is_enabled' => $request->is_enabled ?? $notificationSetting->is_enabled,
        ]);

        return redirect()->route('admin.notification-settings.index')
            ->with('success', 'Notification setting updated successfully.');
    }

    public function destroy(NotificationSetting $notificationSetting)
    {
        $notificationSetting->delete();

        return redirect()->route('admin.notification-settings.index')
            ->with('success', 'Notification setting deleted successfully.');
    }

    // Notification Logs
    public function logs(Request $request)
    {
        $logs = NotificationLog::with('notificationSetting')
            ->when($request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->notification_type, function ($query) use ($request) {
                return $query->where('notification_type', $request->notification_type);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $statuses = NotificationLog::STATUSES;
        $notificationTypes = NotificationSetting::NOTIFICATION_TYPES;

        return view('admin.notifications.logs.index', compact('logs', 'statuses', 'notificationTypes'));
    }

    // Manual Notification Sending
    public function sendTest(Request $request, NotificationSetting $notificationSetting)
    {
        $request->validate([
            'recipient_email' => 'required|email',
            'recipient_phone' => 'nullable|string',
        ]);

        try {
            $this->sendNotification(
                $notificationSetting,
                'test',
                $request->recipient_email,
                $request->recipient_phone,
                ['name' => 'Test User']
            );

            return redirect()->back()->with('success', 'Test notification sent successfully.');
        } catch (\Exception $e) {
            Log::error('Test notification failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to send test notification: ' . $e->getMessage());
        }
    }

    // Bulk Notification Sending
    public function sendBulk(Request $request)
    {
        $request->validate([
            'setting_id' => 'required|exists:notification_settings,id',
            'recipient_type' => 'required|string',
        ]);

        $setting = NotificationSetting::findOrFail($request->setting_id);
        
        if (!$setting->is_enabled) {
            return redirect()->back()->with('error', 'Notification setting is disabled.');
        }

        try {
            $recipients = $this->getRecipients($setting, $request->recipient_type);
            $sentCount = 0;

            foreach ($recipients as $recipient) {
                $this->sendNotification(
                    $setting,
                    $request->recipient_type,
                    $recipient['email'] ?? null,
                    $recipient['phone'] ?? null,
                    $recipient
                );
                $sentCount++;
            }

            return redirect()->back()->with('success', "Successfully queued {$sentCount} notifications.");
        } catch (\Exception $e) {
            Log::error('Bulk notification failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to send bulk notifications: ' . $e->getMessage());
        }
    }

    // Private Methods
    private function sendNotification($setting, $recipientType, $email, $phone, $data)
    {
        $log = NotificationLog::create([
            'notification_setting_id' => $setting->id,
            'recipient_type' => $recipientType,
            'recipient_id' => $data['id'] ?? null,
            'notification_type' => $setting->notification_type,
            'subject' => $this->parseTemplate($setting->template_subject, $data),
            'message' => $this->parseTemplate($setting->template_body, $data),
            'status' => 'pending',
        ]);

        try {
            // Send Email
            if (in_array($setting->notification_type, ['email', 'both']) && $email) {
                Mail::raw($log->message, function ($message) use ($log, $email) {
                    $message->to($email)
                            ->subject($log->subject ?? 'School Notification');
                });
            }

            // Send SMS (placeholder - integrate with SMS gateway)
            if (in_array($setting->notification_type, ['sms', 'both']) && $phone) {
                // SMS integration would go here
                // Example: Twilio, Msg91, etc.
                $this->sendSMS($phone, $log->message);
            }

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'failed_reason' => $e->getMessage(),
                'retry_count' => $log->retry_count + 1,
            ]);
            
            throw $e;
        }
    }

    private function parseTemplate($template, $data)
    {
        if (!$template) return null;
        
        // Replace placeholders with actual data
        $replacements = [
            '{{name}}' => $data['name'] ?? '',
            '{{class}}' => $data['class'] ?? '',
            '{{section}}' => $data['section'] ?? '',
            '{{amount}}' => $data['amount'] ?? '',
            '{{due_date}}' => $data['due_date'] ?? '',
            '{{exam_date}}' => $data['exam_date'] ?? '',
            '{{subject}}' => $data['subject'] ?? '',
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function getRecipients($setting, $recipientType)
    {
        switch ($recipientType) {
            case 'student':
                return Student::select('id', 'name', 'email', 'phone', 'class', 'section')->get()->toArray();
            case 'parent':
                // Assuming parents are linked to students
                return Student::whereNotNull('parent_email')
                    ->select('parent_name as name', 'parent_email as email', 'parent_phone as phone')
                    ->distinct()
                    ->get()
                    ->toArray();
            case 'teacher':
                return Teacher::select('id', 'name', 'email', 'phone')->get()->toArray();
            case 'admin':
                return User::whereHas('roles', function ($query) {
                    $query->where('name', 'admin');
                })->select('id', 'name', 'email')->get()->toArray();
            default:
                return [];
        }
    }

    private function sendSMS($phone, $message)
    {
        // Placeholder for SMS integration
        // Example implementation with Twilio:
        /*
        $client = new \Twilio\Rest\Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
        $client->messages->create(
            $phone,
            [
                'from' => env('TWILIO_PHONE_NUMBER'),
                'body' => $message
            ]
        );
        */
        
        // For now, log the SMS
        Log::info("SMS to {$phone}: {$message}");
    }
}
