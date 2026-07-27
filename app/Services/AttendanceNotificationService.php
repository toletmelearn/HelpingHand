<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\LowAttendanceAlert;
use App\Notifications\AttendanceMarked;
use Carbon\Carbon;

class AttendanceNotificationService
{
    /**
     * Send low attendance alerts to parents
     */
    public function sendLowAttendanceAlerts($threshold = 75, $periodDays = 30)
    {
        // Temporary guard: do not send notifications until reporting policy is aligned.
        return [
            'disabled' => true,
            'message' => 'Attendance notifications are temporarily disabled until attendance reporting policy is aligned.'
        ];
    }
    
    /**
     * Send attendance marked notification to parent. Absent-only (a
     * "present" notification every day would be noise, not signal), and
     * guarded against duplicates so re-marking/editing the same student's
     * attendance later the same day doesn't re-notify the parent.
     *
     * The notification implements ShouldQueue, so this dispatches a job
     * onto the 'database' queue connection rather than sending inline --
     * it will not actually reach anyone until a queue worker is running
     * (php artisan queue:work, or a Supervisor/Windows Service wrapping
     * it in production; nothing runs a worker by default on XAMPP). Mail
     * delivery additionally depends on MAIL_MAILER being a real driver --
     * it's currently set to 'log', so messages land in storage/logs/
     * laravel.log rather than being emailed until that's changed too.
     */
    public function sendAttendanceMarkedNotification($studentId, $date, $status)
    {
        // Marking attendance must never fail because a notification
        // lookup did -- everything here is best-effort and defensive,
        // same as the holiday-guard pattern used elsewhere in attendance
        // marking.
        try {
            if ($status !== 'absent') {
                return ['sent' => false, 'reason' => 'not_absent'];
            }

            $student = Student::find($studentId);
            if (!$student || !$student->parent) {
                return ['sent' => false, 'reason' => 'no_parent_on_file'];
            }

            $alreadySent = $student->parent->notifications()
                ->where('type', AttendanceMarked::class)
                ->where('data->student_id', $studentId)
                ->where('data->date', (string) $date)
                ->exists();

            if ($alreadySent) {
                return ['sent' => false, 'reason' => 'already_sent_for_this_date'];
            }

            Notification::send($student->parent, new AttendanceMarked($student, $date, $status));

            return ['sent' => true];
        } catch (\Throwable $e) {
            Log::warning('Failed to queue attendance-marked notification: ' . $e->getMessage());

            return ['sent' => false, 'reason' => 'dispatch_failed'];
        }
    }
    
    /**
     * Send daily attendance summary to teachers
     */
    public function sendDailyAttendanceSummary($teacherId, $date = null)
    {
        $date = $date ?: Carbon::today();
        // Temporary guard: do not send notifications until reporting policy is aligned.
        return [
            'disabled' => true,
            'message' => 'Attendance notifications are temporarily disabled until attendance reporting policy is aligned.'
        ];
    }
    
    /**
     * Send weekly attendance report to admin
     */
    public function sendWeeklyAttendanceReport($adminId = null)
    {
        $startDate = Carbon::now()->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();
        // Temporary guard: do not send notifications until reporting policy is aligned.
        return [
            'disabled' => true,
            'message' => 'Attendance notifications are temporarily disabled until attendance reporting policy is aligned.'
        ];
    }
    
    /**
     * Send bulk attendance notifications
     */
    public function sendBulkAttendanceNotifications($attendanceRecords)
    {
        // Temporary guard: do not send notifications until reporting policy is aligned.
        return [
            'disabled' => true,
            'message' => 'Attendance notifications are temporarily disabled until attendance reporting policy is aligned.'
        ];
    }
    
    /**
     * Get notification preferences for a user
     */
    public function getUserNotificationPreferences($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return [
                'email' => true,
                'sms' => false,
                'push' => true
            ];
        }
        
        // Return user's notification preferences
        // This would typically come from user settings
        return [
            'email' => true,
            'sms' => $user->notification_preferences['sms'] ?? false,
            'push' => $user->notification_preferences['push'] ?? true
        ];
    }
    
    /**
     * Schedule attendance notifications
     */
    public function scheduleNotifications()
    {
        // Schedule daily low attendance checks
        // Schedule weekly reports
        // Schedule monthly summaries
    }
}