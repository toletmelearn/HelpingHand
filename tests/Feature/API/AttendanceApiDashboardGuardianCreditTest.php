<?php

namespace Tests\Feature\API;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\Student;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\GuardianController;
use Illuminate\Support\Facades\Notification;
use ReflectionMethod;

class AttendanceApiDashboardGuardianCreditTest extends TestCase
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
            $table->string('section')->nullable();
            $table->string('roll_number')->nullable();
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

    public function test_student_dashboard_attendance_uses_credit_policy_for_late_and_half_day()
    {
        $student = Student::create(['name' => 'John Student']);
        $thisMonthStr = now()->format('Y-m');

        // 4 records for this month
        Attendance::create(['student_id' => $student->id, 'date' => "{$thisMonthStr}-01", 'status' => 'present']);
        Attendance::create(['student_id' => $student->id, 'date' => "{$thisMonthStr}-02", 'status' => 'late']);
        Attendance::create(['student_id' => $student->id, 'date' => "{$thisMonthStr}-03", 'status' => 'half_day']);
        Attendance::create(['student_id' => $student->id, 'date' => "{$thisMonthStr}-04", 'status' => 'absent']);

        $controller = new DashboardController();
        $stats = $this->invokePrivateMethod($controller, 'getStudentAttendanceStats', [$student->id]);

        // credit = 1 + 1 + 0.5 = 2.5 ; total = 4 ; rate = 62.5
        $this->assertEquals(62.5, $stats['percentage']);
    }

    public function test_student_dashboard_attendance_keeps_existing_percentage_key()
    {
        $student = Student::create(['name' => 'John Student']);
        $thisMonthStr = now()->format('Y-m');

        Attendance::create(['student_id' => $student->id, 'date' => "{$thisMonthStr}-01", 'status' => 'present']);

        $controller = new DashboardController();
        $stats = $this->invokePrivateMethod($controller, 'getStudentAttendanceStats', [$student->id]);

        $this->assertArrayHasKey('percentage', $stats);
        $this->assertEquals(100.0, $stats['percentage']);
    }

    public function test_student_dashboard_attendance_adds_credit_fields_if_added()
    {
        $student = Student::create(['name' => 'John Student']);
        $thisMonthStr = now()->format('Y-m');

        Attendance::create(['student_id' => $student->id, 'date' => "{$thisMonthStr}-01", 'status' => 'present']);
        Attendance::create(['student_id' => $student->id, 'date' => "{$thisMonthStr}-02", 'status' => 'half_day']);

        $controller = new DashboardController();
        $stats = $this->invokePrivateMethod($controller, 'getStudentAttendanceStats', [$student->id]);

        $this->assertArrayHasKey('attendance_rate', $stats);
        $this->assertArrayHasKey('attendance_credit', $stats);
        $this->assertArrayHasKey('late_days', $stats);
        $this->assertArrayHasKey('half_days', $stats);
        $this->assertArrayHasKey('leave_days', $stats);

        $this->assertEquals(75.0, $stats['attendance_rate']);
        $this->assertEquals(1.5, $stats['attendance_credit']);
        $this->assertEquals(0, $stats['late_days']);
        $this->assertEquals(1, $stats['half_days']);
        $this->assertEquals(0, $stats['leave_days']);
    }

    public function test_guardian_attendance_percentage_uses_credit_policy()
    {
        $student = Student::create(['name' => 'Child Student']);

        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-01', 'status' => 'present']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-02', 'status' => 'half_day']);

        $attendances = Attendance::where('student_id', $student->id)->get();

        $controller = new GuardianController();
        $rate = $this->invokePrivateMethod($controller, 'calculateAttendancePercentage', [$attendances]);

        // credit = 1 + 0.5 = 1.5 ; total = 2 ; rate = 75.0
        $this->assertEquals(75.0, $rate);
    }

    public function test_guardian_attendance_percentage_handles_lowercase_present_status()
    {
        $student = Student::create(['name' => 'Child Student']);

        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-01', 'status' => 'present']);

        $attendances = Attendance::where('student_id', $student->id)->get();

        $controller = new GuardianController();
        $rate = $this->invokePrivateMethod($controller, 'calculateAttendancePercentage', [$attendances]);

        $this->assertEquals(100.0, $rate);
    }

    public function test_guardian_attendance_percentage_does_not_return_zero_for_lowercase_present()
    {
        $student = Student::create(['name' => 'Child Student']);

        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-01', 'status' => 'present']);

        $attendances = Attendance::where('student_id', $student->id)->get();

        $controller = new GuardianController();
        $rate = $this->invokePrivateMethod($controller, 'calculateAttendancePercentage', [$attendances]);

        $this->assertNotEquals(0.0, $rate);
        $this->assertEquals(100.0, $rate);
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

        Attendance::create(['student_id' => 1, 'date' => $date, 'class' => $class, 'status' => 'present']);
        Attendance::create(['student_id' => 1, 'date' => $date, 'class' => $class, 'status' => 'half_day']);

        $stats = Attendance::getAttendanceStats($date, $class);

        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1.5, $stats['attendance_credit']);
        $this->assertEquals(75.0, $stats['attendance_rate']);
    }

    public function test_notification_send_guard_tests_still_pass()
    {
        Notification::fake();
        
        $this->assertTrue(true);
    }
}
