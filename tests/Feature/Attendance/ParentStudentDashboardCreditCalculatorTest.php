<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\Student;
use App\Http\Controllers\Admin\RoleDashboardController;
use App\Http\Controllers\ParentController;
use App\Support\Attendance\AttendanceCreditCalculator;
use Illuminate\Support\Facades\Notification;
use ReflectionMethod;

class ParentStudentDashboardCreditCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('students', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('class')->nullable();
            $table->string('section')->nullable();
            $table->string('roll_number')->nullable();
            $table->integer('parent_id')->nullable();
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

    private function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $method = new ReflectionMethod($object, $methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    public function test_role_dashboard_student_rate_uses_credit_policy_for_late_and_half_day()
    {
        $student = Student::create(['name' => 'John Student']);

        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-01', 'status' => 'present']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-02', 'status' => 'late']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-03', 'status' => 'half_day']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-04', 'status' => 'absent']);

        $controller = new RoleDashboardController();
        $rate = $this->invokePrivateMethod($controller, 'getStudentAttendanceRate', [$student->id]);

        // credit = 1 + 1 + 0.5 = 2.5 ; total = 4 ; rate = 62.5
        $this->assertEquals(62.5, $rate);
    }

    public function test_role_dashboard_student_rate_keeps_leave_zero_credit()
    {
        $student = Student::create(['name' => 'John Student']);

        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-01', 'status' => 'present']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-02', 'status' => 'leave']);

        $controller = new RoleDashboardController();
        $rate = $this->invokePrivateMethod($controller, 'getStudentAttendanceRate', [$student->id]);

        // credit = 1 ; total = 2 ; rate = 50.0
        $this->assertEquals(50.0, $rate);
    }

    public function test_parent_child_attendance_percentage_uses_credit_policy_for_late_and_half_day()
    {
        $student = Student::create(['name' => 'Child Student']);

        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-01', 'status' => 'present']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-02', 'status' => 'late']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-03', 'status' => 'half_day']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-04', 'status' => 'absent']);

        $controller = new ParentController();
        $rate = $this->invokePrivateMethod($controller, 'getChildAttendancePercentage', [$student]);

        // credit = 2.5 ; total = 4 ; rate = 62.5
        $this->assertEquals(62.5, $rate);
    }

    public function test_parent_child_attendance_percentage_keeps_leave_zero_credit()
    {
        $student = Student::create(['name' => 'Child Student']);

        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-01', 'status' => 'present']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-02', 'status' => 'leave']);

        $controller = new ParentController();
        $rate = $this->invokePrivateMethod($controller, 'getChildAttendancePercentage', [$student]);

        // credit = 1 ; total = 2 ; rate = 50.0
        $this->assertEquals(50.0, $rate);
    }

    public function test_parent_child_attendance_does_not_return_zero_for_lowercase_present()
    {
        $student = Student::create(['name' => 'Child Student']);

        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-01', 'status' => 'present']);

        $controller = new ParentController();
        $rate = $this->invokePrivateMethod($controller, 'getChildAttendancePercentage', [$student]);

        $this->assertEquals(100.0, $rate);
    }

    public function test_attendance_credit_calculator_tests_still_pass()
    {
        $statuses = ['present', 'late', 'half_day', 'absent'];
        $sum = AttendanceCreditCalculator::summarize($statuses);
        $this->assertEquals(2.5, $sum['attendance_credit']);
        $this->assertEquals(62.5, $sum['attendance_rate']);
    }

    public function test_attendance_notification_send_guard_tests_still_pass()
    {
        Notification::fake();
        // Assert NotificationService daily summary/send is guarded
        $notificationService = new \App\Services\AttendanceNotificationService();
        $res = $notificationService->sendLowAttendanceAlerts();
        $this->assertTrue($res['disabled']);
        Notification::assertNothingSent();
    }
}
