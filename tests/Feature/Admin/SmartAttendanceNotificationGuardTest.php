<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\Request;
use App\Http\Controllers\Admin\SmartAttendanceController;

class SmartAttendanceNotificationGuardTest extends TestCase
{
    public function test_smart_attendance_alert_send_is_guarded_if_controller_changed()
    {
        Notification::fake();

        $controller = new SmartAttendanceController();

        $request = Request::create('/admin/attendance/alerts', 'POST', [
            'threshold' => 75
        ]);

        $response = $controller->sendAttendanceAlerts($request);

        // Controller returns a redirect back with warning message when guarded
        $this->assertTrue(method_exists($response, 'getSession'));

        Notification::assertNothingSent();
    }
}
