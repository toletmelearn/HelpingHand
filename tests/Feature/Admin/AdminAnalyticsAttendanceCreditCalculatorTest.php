<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\Student;
use App\Http\Controllers\Admin\AISmartFeaturesController;
use App\Http\Controllers\Admin\AdvancedReportController;
use App\Http\Controllers\Admin\PerformanceAnalyticsController;
use App\Services\ProfessionalDashboardService;
use App\Support\Attendance\AttendanceCreditCalculator;
use Illuminate\Support\Facades\Notification;
use ReflectionMethod;

class AdminAnalyticsAttendanceCreditCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Carbon\Carbon::setTestNow('2026-06-08 00:00:00');

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
            $table->string('mobile')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->date('date');
            $table->string('status')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
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
        \Carbon\Carbon::setTestNow();

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

    public function test_ai_smart_attendance_warnings_use_credit_policy()
    {
        $student = Student::create(['name' => 'John Warning', 'mobile' => '1234567890']);

        // Today is 2026-06-08 (from system metadata), sub 30 days is in range
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-01', 'status' => 'present']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-02', 'status' => 'late']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-03', 'status' => 'half_day']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-04', 'status' => 'absent']);

        $controller = new AISmartFeaturesController();
        $warnings = $this->invokePrivateMethod($controller, 'getAttendanceWarnings');

        // Total credit = 1 (present) + 1 (late) + 0.5 (half_day) = 2.5
        // Total days = 4
        // Rate = 62.5% (< 75% threshold, so should trigger warning)
        $this->assertCount(1, $warnings);
        $this->assertEquals(62.5, $warnings[0]['attendance_percentage']);
        $this->assertEquals(4, $warnings[0]['total_days']);
        $this->assertEquals(1, $warnings[0]['present_days']);
        $this->assertEquals(1, $warnings[0]['absent_days']); // calculator counts absent status = 1
    }

    public function test_advanced_report_attendance_analytics_use_credit_policy()
    {
        // 2 present, 1 late, 1 half_day, 1 absent => credit = 1 + 1 + 1 + 0.5 = 3.5, total = 5, rate = 70.0%
        Attendance::forceCreate(['date' => '2026-06-01', 'status' => 'present', 'class_id' => 1]);
        Attendance::forceCreate(['date' => '2026-06-02', 'status' => 'present', 'class_id' => 1]);
        Attendance::forceCreate(['date' => '2026-06-03', 'status' => 'late', 'class_id' => 1]);
        Attendance::forceCreate(['date' => '2026-06-04', 'status' => 'half_day', 'class_id' => 1]);
        Attendance::forceCreate(['date' => '2026-06-05', 'status' => 'absent', 'class_id' => 1]);

        $controller = new AdvancedReportController();
        $dateFilter = ['2026-06-01', '2026-06-07'];
        
        $stats = $this->invokePrivateMethod($controller, 'getAttendanceAnalytics', [null, 1, null, $dateFilter]);
        
        $this->assertEquals(70.0, $stats['attendance_rate']);
        $this->assertEquals(5, $stats['total_attendance']);
        $this->assertEquals(2, $stats['present_count']);
        $this->assertEquals(1, $stats['absent_count']);
        $this->assertEquals(1, $stats['late_arrivals']);
        $this->assertEquals(3.5, $stats['attendance_credit']);
        $this->assertEquals(1, $stats['half_days']);
    }

    public function test_performance_analytics_overall_rate_uses_credit_policy_and_filters_late_correctly()
    {
        // Inside range
        Attendance::create(['date' => '2026-06-01', 'status' => 'present']);
        Attendance::create(['date' => '2026-06-02', 'status' => 'late']);
        Attendance::create(['date' => '2026-06-03', 'status' => 'half_day']);
        Attendance::create(['date' => '2026-06-04', 'status' => 'absent']);

        // Outside range (should not be matched by date range, ensuring no unbracketed late matches)
        Attendance::create(['date' => '2026-05-01', 'status' => 'late']);

        $controller = new PerformanceAnalyticsController();
        $rate = $this->invokePrivateMethod($controller, 'getOverallAttendanceRate', ['2026-06-01', '2026-06-07']);

        // credit = 1 (present) + 1 (late) + 0.5 (half_day) = 2.5
        // total = 4
        // rate = 62.5%
        $this->assertEquals(62.5, $rate);
    }

    public function test_professional_dashboard_today_rate_uses_credit_policy()
    {
        // today's records
        Attendance::create(['date' => today()->format('Y-m-d'), 'status' => 'present']);
        Attendance::create(['date' => today()->format('Y-m-d'), 'status' => 'late']);
        Attendance::create(['date' => today()->format('Y-m-d'), 'status' => 'half_day']);
        Attendance::create(['date' => today()->format('Y-m-d'), 'status' => 'absent']);

        // other day
        Attendance::create(['date' => today()->subDay()->format('Y-m-d'), 'status' => 'present']);

        $service = new ProfessionalDashboardService();
        $rate = $this->invokePrivateMethod($service, 'getTodayAttendanceRate');

        // credit = 2.5, total = 4, rate = 62.5%
        $this->assertEquals(62.5, $rate);
    }

    public function test_professional_dashboard_monthly_rate_uses_credit_policy()
    {
        // this month
        $start = now()->startOfMonth()->format('Y-m-d');
        Attendance::create(['date' => $start, 'status' => 'present']);
        Attendance::create(['date' => $start, 'status' => 'late']);
        Attendance::create(['date' => $start, 'status' => 'half_day']);
        Attendance::create(['date' => $start, 'status' => 'absent']);

        // previous month
        $prev = now()->subMonth()->startOfMonth()->format('Y-m-d');
        Attendance::create(['date' => $prev, 'status' => 'present']);

        $service = new ProfessionalDashboardService();
        $rate = $this->invokePrivateMethod($service, 'getMonthlyAttendanceRate');

        // credit = 2.5, total = 4, rate = 62.5%
        $this->assertEquals(62.5, $rate);
    }

    public function test_legacy_leave_remains_zero_credit()
    {
        Attendance::create(['date' => today()->format('Y-m-d'), 'status' => 'present']);
        Attendance::create(['date' => today()->format('Y-m-d'), 'status' => 'leave']);

        $service = new ProfessionalDashboardService();
        $rate = $this->invokePrivateMethod($service, 'getTodayAttendanceRate');

        // credit = 1.0 (present), leave = 0.0, total = 2, rate = 50.0%
        $this->assertEquals(50.0, $rate);
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
        $notificationService = new \App\Services\AttendanceNotificationService();
        $res = $notificationService->sendLowAttendanceAlerts();
        $this->assertTrue($res['disabled']);
        Notification::assertNothingSent();
    }

    public function test_parent_student_dashboard_credit_tests_still_pass()
    {
        $student = Student::create(['name' => 'John Student']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-01', 'status' => 'present']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-02', 'status' => 'late']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-03', 'status' => 'half_day']);
        Attendance::create(['student_id' => $student->id, 'date' => '2026-06-04', 'status' => 'absent']);

        $controller = new \App\Http\Controllers\Admin\RoleDashboardController();
        $rate = $this->invokePrivateMethod($controller, 'getStudentAttendanceRate', [$student->id]);
        $this->assertEquals(62.5, $rate);
    }
}
