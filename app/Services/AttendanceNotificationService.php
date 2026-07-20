<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Attendance;
use App\Models\User;
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
     * Send attendance marked notification to parent
     */
    public function sendAttendanceMarkedNotification($studentId, $date, $status)
    {
        // Temporary guard: do not send notifications until reporting policy is aligned.
        return [
            'disabled' => true,
            'message' => 'Attendance notifications are temporarily disabled until attendance reporting policy is aligned.'
        ];
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