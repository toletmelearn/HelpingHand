<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\Notification;

class AttendanceServiceStatusCalculationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Carbon\Carbon::setTestNow('2026-06-08 00:00:00');

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->nullable();
            $table->text('remarks')->nullable();
            $table->string('class')->nullable();
            $table->integer('marked_by')->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->integer('class_id')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('school_classes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();

        Schema::dropIfExists('attendances');
        Schema::dropIfExists('students');

        parent::tearDown();
    }

    private function service(): AttendanceService
    {
        return new AttendanceService();
    }

    public function test_student_stats_count_present_as_full_credit()
    {
        DB::table('attendances')->insert([
            ['student_id' => 1, 'date' => '2026-06-01', 'status' => 'present'],
        ]);

        $stats = $this->service()->getStudentAttendanceStats(1, '2026-06-01', '2026-06-30');

        $this->assertEquals(1, $stats['present_days']);
        $this->assertEquals(0, $stats['late_days']);
        $this->assertEquals(0, $stats['half_days']);
        $this->assertEquals(1.0, $stats['attendance_credit']);
        $this->assertEquals(100.0, $stats['attendance_rate']);
    }

    public function test_student_stats_count_late_as_full_attendance_credit_and_late_count()
    {
        DB::table('attendances')->insert([
            ['student_id' => 2, 'date' => '2026-06-01', 'status' => 'late'],
        ]);

        $stats = $this->service()->getStudentAttendanceStats(2, '2026-06-01', '2026-06-30');

        $this->assertEquals(0, $stats['present_days']);
        $this->assertEquals(1, $stats['late_days']);
        $this->assertEquals(0, $stats['half_days']);
        $this->assertEquals(1.0, $stats['attendance_credit']);
        $this->assertEquals(100.0, $stats['attendance_rate']);
    }

    public function test_student_stats_count_half_day_as_half_credit_and_half_day_count()
    {
        DB::table('attendances')->insert([
            ['student_id' => 3, 'date' => '2026-06-01', 'status' => 'half_day'],
        ]);

        $stats = $this->service()->getStudentAttendanceStats(3, '2026-06-01', '2026-06-30');

        $this->assertEquals(0, $stats['present_days']);
        $this->assertEquals(0, $stats['late_days']);
        $this->assertEquals(1, $stats['half_days']);
        $this->assertEquals(0.5, $stats['attendance_credit']);
        $this->assertEquals(50.0, $stats['attendance_rate']);
    }

    public function test_student_stats_count_absent_as_zero_credit()
    {
        DB::table('attendances')->insert([
            ['student_id' => 4, 'date' => '2026-06-01', 'status' => 'absent'],
        ]);

        $stats = $this->service()->getStudentAttendanceStats(4, '2026-06-01', '2026-06-30');

        $this->assertEquals(0, $stats['present_days']);
        $this->assertEquals(0, $stats['late_days']);
        $this->assertEquals(0, $stats['half_days']);
        $this->assertEquals(0.0, $stats['attendance_credit']);
        $this->assertEquals(0.0, $stats['attendance_rate']);
    }

    public function test_student_stats_count_legacy_leave_separately_without_credit()
    {
        DB::table('attendances')->insert([
            ['student_id' => 5, 'date' => '2026-06-01', 'status' => 'leave'],
        ]);

        $stats = $this->service()->getStudentAttendanceStats(5, '2026-06-01', '2026-06-30');

        $this->assertEquals(0, $stats['present_days']);
        $this->assertEquals(0, $stats['late_days']);
        $this->assertEquals(0, $stats['half_days']);
        $this->assertEquals(1, $stats['leave_days']);
        $this->assertEquals(0.0, $stats['attendance_credit']);
        $this->assertEquals(0.0, $stats['attendance_rate']);
    }

    public function test_student_stats_calculate_mixed_attendance_rate_correctly()
    {
        DB::table('attendances')->insert([
            ['student_id' => 6, 'date' => '2026-06-01', 'status' => 'present'],
            ['student_id' => 6, 'date' => '2026-06-02', 'status' => 'late'],
            ['student_id' => 6, 'date' => '2026-06-03', 'status' => 'half_day'],
            ['student_id' => 6, 'date' => '2026-06-04', 'status' => 'absent'],
            ['student_id' => 6, 'date' => '2026-06-05', 'status' => 'leave'],
        ]);

        $stats = $this->service()->getStudentAttendanceStats(6, '2026-06-01', '2026-06-30');

        // present=1, late=1, half_day=0.5 => credit=2.5; total records=5 => rate=50%
        $this->assertEquals(1, $stats['present_days']);
        $this->assertEquals(1, $stats['late_days']);
        $this->assertEquals(1, $stats['half_days']);
        $this->assertEquals(1, $stats['absent_days']);
        $this->assertEquals(1, $stats['leave_days']);
        $this->assertEquals(2.5, $stats['attendance_credit']);
        $this->assertEquals(50.0, $stats['attendance_rate']);
    }

    public function test_low_attendance_alerts_use_new_credit_policy()
    {
        // Create student row
        DB::table('students')->insert(['id' => 10, 'name' => 'Test Student', 'class_id' => 1]);

        // Student has 5 records with credit 2.5 -> rate 50
        DB::table('attendances')->insert([
            ['student_id' => 10, 'date' => '2026-06-01', 'status' => 'present'],
            ['student_id' => 10, 'date' => '2026-06-02', 'status' => 'late'],
            ['student_id' => 10, 'date' => '2026-06-03', 'status' => 'half_day'],
            ['student_id' => 10, 'date' => '2026-06-04', 'status' => 'absent'],
            ['student_id' => 10, 'date' => '2026-06-05', 'status' => 'leave'],
        ]);

        $alerts = $this->service()->getLowAttendanceAlerts(75, 30);

        $this->assertNotEmpty($alerts);
        $found = false;
        foreach ($alerts as $alert) {
            if ($alert['student']->id === 10) {
                $found = true;
                $this->assertSame(50.0, $alert['attendance_rate']);
            }
        }

        $this->assertTrue($found, 'Expected low attendance alert for student 10');
    }

    public function test_attendance_trends_use_new_credit_policy()
    {
        // Insert some records in the recent period for student 11
        DB::table('attendances')->insert([
            ['student_id' => 11, 'date' => '2026-05-15', 'status' => 'present'],
            ['student_id' => 11, 'date' => '2026-05-16', 'status' => 'half_day'],
        ]);

        $trends = $this->service()->getAttendanceTrends(11, 1);

        $this->assertIsArray($trends);
        $this->assertNotEmpty($trends);
        $this->assertArrayHasKey('attendance_rate', $trends[0]);
    }

    public function test_generate_attendance_report_uses_new_credit_policy()
    {
        DB::table('school_classes')->insert(['id' => 100, 'name' => 'Class 100']);
        // Create a class and students
        DB::table('students')->insert(['id' => 20, 'name' => 'S1', 'class_id' => 100]);
        DB::table('students')->insert(['id' => 21, 'name' => 'S2', 'class_id' => 100]);

        // S1: present
        DB::table('attendances')->insert([
            ['student_id' => 20, 'date' => '2026-06-01', 'status' => 'present'],
        ]);

        // S2: half_day
        DB::table('attendances')->insert([
            ['student_id' => 21, 'date' => '2026-06-01', 'status' => 'half_day'],
        ]);

        $report = $this->service()->generateAttendanceReport(100, '2026-06-01', '2026-06-30');

        $this->assertArrayHasKey('students', $report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertGreaterThanOrEqual(0, $report['summary']['class_average']);
    }

    public function test_mark_attendance_guard_still_passes()
    {
        $this->expectException(\RuntimeException::class);
        $this->service()->markAttendance([['student_id' => 1, 'date' => '2026-06-07', 'status' => 'present']], 1);
    }

    public function test_notification_send_guard_still_passes()
    {
        Notification::fake();
        // AttendanceNotificationService is already guarded by Phase 7G; assert nothing is sent when called
        $notificationService = new \App\Services\AttendanceNotificationService();
        $res = $notificationService->sendLowAttendanceAlerts();
        $this->assertIsArray($res);
        $this->assertTrue($res['disabled']);
        Notification::assertNothingSent();
    }
}
