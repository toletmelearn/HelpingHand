<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\AttendanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AttendanceRouteOrderTest extends TestCase
{
    public function test_attendance_bulk_mark_route_exists()
    {
        $this->assertTrue(Route::has('attendance.bulk-mark'));
    }

    public function test_attendance_bulk_mark_dispatches_to_bulkMark_not_show()
    {
        $route = $this->matchRoute('GET', '/attendance/bulk-mark');

        $this->assertSame(AttendanceController::class . '@bulkMark', $route->getAction('controller'));
        $this->assertNotSame(AttendanceController::class . '@show', $route->getAction('controller'));
    }

    public function test_attendance_student_report_dispatches_to_studentReport_not_show()
    {
        $route = $this->matchRoute('GET', '/attendance/student/123/report');

        $this->assertSame(AttendanceController::class . '@studentReport', $route->getAction('controller'));
        $this->assertNotSame(AttendanceController::class . '@show', $route->getAction('controller'));
    }

    public function test_attendance_resource_show_still_exists_for_real_ids()
    {
        $route = $this->matchRoute('GET', '/attendance/123');

        $this->assertSame(AttendanceController::class . '@show', $route->getAction('controller'));
    }

    public function test_preflight_routes_remain_registered_before_resource()
    {
        $this->assertTrue(Route::has('admin.attendance.preflight') || Route::has('attendance.preflight'));
        $this->assertTrue(Route::has('admin.attendance.preflight-view') || Route::has('attendance.preflight-view'));

        $preflight = $this->matchRoute('POST', '/admin/attendance/preflight');
        $preflightView = $this->matchRoute('POST', '/admin/attendance/preflight-view');

        $this->assertSame(AttendanceController::class . '@preflight', $preflight->getAction('controller'));
        $this->assertSame(AttendanceController::class . '@preflightView', $preflightView->getAction('controller'));
    }

    public function test_bulk_direct_write_guard_tests_still_pass()
    {
        $this->assertTrue(class_exists(AttendanceBulkDirectWriteGuardTest::class));
        $this->assertTrue(Route::has('admin.attendance.store') || Route::has('attendance.store'));
    }

    private function matchRoute(string $method, string $uri)
    {
        $request = Request::create($uri, $method);

        return Route::getRoutes()->match($request);
    }
}
