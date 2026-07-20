<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\Student;
use App\Http\Controllers\AttendanceController;
use ReflectionMethod;

class AttendanceControllerStatsCreditCalculatorTest extends TestCase
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

    private function getStats($date = null)
    {
        $controller = new AttendanceController();
        $method = new ReflectionMethod($controller, 'calculateAttendanceStats');
        $method->setAccessible(true);
        return $method->invoke($controller, $date);
    }

    public function test_attendance_index_stats_use_credit_policy_for_late_and_half_day()
    {
        $date = '2026-06-01';

        // Total 4 students in database
        Student::create(['name' => 'S1']);
        Student::create(['name' => 'S2']);
        Student::create(['name' => 'S3']);
        Student::create(['name' => 'S4']);

        // 4 attendance records for this date
        Attendance::create(['date' => $date, 'status' => 'present']);
        Attendance::create(['date' => $date, 'status' => 'late']);
        Attendance::create(['date' => $date, 'status' => 'half_day']);
        Attendance::create(['date' => $date, 'status' => 'absent']);

        $stats = $this->getStats($date);

        // credit = 1 (present) + 1 (late) + 0.5 (half_day) = 2.5 ; total = 4 ; rate = 62.5
        $this->assertEquals(62.5, $stats['attendance_rate']);
    }

    public function test_attendance_index_stats_keep_present_absent_late_half_day_counts()
    {
        $date = '2026-06-01';

        Student::create(['name' => 'S1']);

        Attendance::create(['date' => $date, 'status' => 'present']);
        Attendance::create(['date' => $date, 'status' => 'late']);
        Attendance::create(['date' => $date, 'status' => 'half_day']);
        Attendance::create(['date' => $date, 'status' => 'absent']);

        $stats = $this->getStats($date);

        $this->assertEquals(1, $stats['present_today']);
        $this->assertEquals(1, $stats['absent']);
        $this->assertEquals(1, $stats['late']);
        $this->assertEquals(1, $stats['half_day']);
    }

    public function test_attendance_index_stats_keep_leave_as_zero_credit_if_present()
    {
        $date = '2026-06-01';

        Student::create(['name' => 'S1']);

        Attendance::create(['date' => $date, 'status' => 'present']);
        Attendance::create(['date' => $date, 'status' => 'leave']);

        $stats = $this->getStats($date);

        // credit = 1.0 ; total = 2 ; rate = 50.0
        $this->assertEquals(50.0, $stats['attendance_rate']);
        $this->assertEquals(1, $stats['leave']);
    }

    public function test_attendance_index_stats_include_attendance_credit_if_added()
    {
        $date = '2026-06-01';

        Student::create(['name' => 'S1']);

        Attendance::create(['date' => $date, 'status' => 'present']);
        Attendance::create(['date' => $date, 'status' => 'half_day']);

        $stats = $this->getStats($date);

        $this->assertEquals(1.5, $stats['attendance_credit']);
    }

    public function test_attendance_credit_calculator_tests_still_pass()
    {
        $statuses = ['present', 'late', 'half_day', 'absent'];
        $sum = \App\Support\Attendance\AttendanceCreditCalculator::summarize($statuses);
        $this->assertEquals(2.5, $sum['attendance_credit']);
        $this->assertEquals(62.5, $sum['attendance_rate']);
    }

    public function test_attendance_model_helper_credit_tests_still_pass()
    {
        $date = '2026-06-01';
        $class = '10A';
        Attendance::create(['date' => $date, 'class' => $class, 'status' => 'present']);
        Attendance::create(['date' => $date, 'class' => $class, 'status' => 'late']);
        $stats = Attendance::getAttendanceStats($date, $class);
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(2.0, $stats['attendance_credit']);
        $this->assertEquals(100.0, $stats['attendance_rate']);
    }

    public function test_attendance_service_status_calculation_tests_still_pass()
    {
        $statuses = ['present', 'half_day'];
        $sum = \App\Support\Attendance\AttendanceCreditCalculator::summarize($statuses);
        $this->assertEquals(1.5, $sum['attendance_credit']);
        $this->assertEquals(75.0, $sum['attendance_rate']);
    }

    public function test_notification_send_guard_tests_still_pass()
    {
        \Illuminate\Support\Facades\Notification::fake();
        $notificationService = new \App\Services\AttendanceNotificationService();
        $res = $notificationService->sendLowAttendanceAlerts();
        $this->assertIsArray($res);
        $this->assertTrue($res['disabled']);
        \Illuminate\Support\Facades\Notification::assertNothingSent();
    }
}
