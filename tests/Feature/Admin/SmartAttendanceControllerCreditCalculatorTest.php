<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\Student;
use App\Http\Controllers\Admin\SmartAttendanceController;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;
use ReflectionMethod;

class SmartAttendanceControllerCreditCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        // Create tables on SQLite connection
        Schema::create('students', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('class')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->date('date');
            $table->string('status')->nullable();
            $table->string('class')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('field_name')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('action')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('students');
        Schema::dropIfExists('audit_logs');
        parent::tearDown();
    }

    private function invokeMethod($methodName, array $parameters = [])
    {
        $controller = new SmartAttendanceController();
        $method = new ReflectionMethod($controller, $methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($controller, $parameters);
    }

    public function test_smart_attendance_statistics_use_credit_policy_for_late_and_half_day()
    {
        $date = '2026-06-01';

        // 4 attendance records for this date
        Attendance::create(['date' => $date, 'status' => 'present']);
        Attendance::create(['date' => $date, 'status' => 'late']);
        Attendance::create(['date' => $date, 'status' => 'half_day']);
        Attendance::create(['date' => $date, 'status' => 'absent']);

        $stats = $this->invokeMethod('getAttendanceStatistics', [$date]);

        // credit = 1 (present) + 1 (late) + 0.5 (half_day) = 2.5 ; total = 4 ; rate = 62.5
        $this->assertEquals(62.5, $stats['attendance_rate']);
    }

    public function test_smart_attendance_statistics_keep_status_counts()
    {
        $date = '2026-06-01';

        Attendance::create(['date' => $date, 'status' => 'present']);
        Attendance::create(['date' => $date, 'status' => 'late']);
        Attendance::create(['date' => $date, 'status' => 'half_day']);
        Attendance::create(['date' => $date, 'status' => 'absent']);
        Attendance::create(['date' => $date, 'status' => 'leave']);

        $stats = $this->invokeMethod('getAttendanceStatistics', [$date]);

        $this->assertEquals(1, $stats['present']);
        $this->assertEquals(1, $stats['absent']);
        $this->assertEquals(1, $stats['late']);
        $this->assertEquals(1, $stats['half_day']);
        $this->assertEquals(1, $stats['leave']);
        $this->assertEquals(5, $stats['total']);
    }

    public function test_smart_attendance_trends_use_credit_policy()
    {
        $date = now()->toDateString();
        
        Attendance::create(['date' => $date, 'status' => 'present']);
        Attendance::create(['date' => $date, 'status' => 'half_day']); // credit = 1.5, total = 2 -> 75%

        $trends = $this->invokeMethod('getAttendanceTrends');

        $this->assertContains($date, $trends['dates']);
        $this->assertContains(75.0, $trends['attendance_rates']);
    }

    public function test_smart_attendance_warnings_use_credit_policy()
    {
        $student = Student::create(['name' => 'John Doe']);

        $date1 = now()->toDateString();
        $date2 = now()->subDay()->toDateString();

        Attendance::create(['student_id' => $student->id, 'date' => $date1, 'status' => 'half_day']); // 0.5
        Attendance::create(['student_id' => $student->id, 'date' => $date2, 'status' => 'absent']); // 0.0
        // total = 2, credit = 0.5 -> rate = 25% (< 75%)

        $warnings = $this->invokeMethod('getAttendanceWarnings');

        $this->assertNotEmpty($warnings);
        $this->assertEquals(25.0, $warnings->first()['percentage']);
    }

    public function test_class_wise_attendance_uses_credit_policy()
    {
        $student = Student::create(['name' => 'S1', 'class' => '10A']);
        $date = now()->subDays(5)->toDateString();

        Attendance::create(['student_id' => $student->id, 'date' => $date, 'status' => 'half_day']); // credit = 0.5, total = 1 -> 50%

        $stats = $this->invokeMethod('getClassWiseAttendance');

        $this->assertNotEmpty($stats);
        $this->assertEquals(50.0, $stats->first()['attendance_rate']);
    }

    public function test_monthly_attendance_trends_use_credit_policy()
    {
        $date = now()->toDateString();
        Attendance::create(['date' => $date, 'status' => 'half_day']); // credit = 0.5, total = 1 -> 50%

        $trends = $this->invokeMethod('getMonthlyAttendanceTrends');

        $this->assertNotEmpty($trends);
        // The current month is the last element in the trends array (subMonths(0))
        $currentMonthTrend = last($trends);
        $this->assertEquals(50.0, $currentMonthTrend['attendance_rate']);
    }

    public function test_smart_attendance_notification_send_guard_still_passes()
    {
        Notification::fake();
        $controller = new SmartAttendanceController();
        $request = new \Illuminate\Http\Request();
        
        $response = $controller->sendAttendanceAlerts($request);

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertTrue(method_exists($response, 'getSession') || $response->isRedirection());
        
        Notification::assertNothingSent();
    }

    public function test_attendance_credit_calculator_tests_still_pass()
    {
        $statuses = ['present', 'late', 'half_day', 'absent'];
        $sum = \App\Support\Attendance\AttendanceCreditCalculator::summarize($statuses);
        $this->assertEquals(2.5, $sum['attendance_credit']);
        $this->assertEquals(62.5, $sum['attendance_rate']);
    }
}
