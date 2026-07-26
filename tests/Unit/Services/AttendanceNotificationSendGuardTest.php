<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Support\Facades\Notification;
use App\Services\AttendanceNotificationService;
use App\Services\AttendanceService;

class AttendanceNotificationSendGuardTest extends TestCase
{
    public function test_attendance_notification_service_low_attendance_send_is_guarded()
    {
        Notification::fake();

        $svc = new AttendanceNotificationService();
        $res = $svc->sendLowAttendanceAlerts();

        $this->assertIsArray($res);
        $this->assertTrue($res['disabled']);
        $this->assertStringContainsString('temporarily disabled', $res['message']);

        Notification::assertNothingSent();
    }

    /**
     * sendAttendanceMarkedNotification() was re-enabled for real in
     * remediation Task 5 -- it's no longer part of this blanket guard.
     * See tests/Feature/Attendance/AttendanceMarkedNotificationTest.php.
     */
    public function test_attendance_notification_service_daily_summary_is_guarded()
    {
        Notification::fake();
        $svc = new AttendanceNotificationService();

        $res = $svc->sendDailyAttendanceSummary(1);
        $this->assertIsArray($res);
        $this->assertTrue($res['disabled']);

        Notification::assertNothingSent();
    }

    public function test_attendance_notification_service_weekly_report_is_guarded()
    {
        Notification::fake();
        $svc = new AttendanceNotificationService();

        $res = $svc->sendWeeklyAttendanceReport();
        $this->assertIsArray($res);
        $this->assertTrue($res['disabled']);

        Notification::assertNothingSent();
    }

    public function test_attendance_notification_service_bulk_notifications_are_guarded()
    {
        Notification::fake();
        $svc = new AttendanceNotificationService();

        $res = $svc->sendBulkAttendanceNotifications([]);
        $this->assertIsArray($res);
        $this->assertTrue($res['disabled']);

        Notification::assertNothingSent();
    }

    public function test_attendance_service_mark_attendance_guard_still_passes()
    {
        $this->expectException(\RuntimeException::class);

        $svc = new AttendanceService();
        $svc->markAttendance([['student_id' => 1, 'date' => '2026-01-01', 'status' => 'present']]);
    }
}
