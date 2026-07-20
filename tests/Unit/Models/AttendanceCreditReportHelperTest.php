<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Support\Attendance\AttendanceCreditCalculator;

class AttendanceCreditReportHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use in-memory sqlite for isolation
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        // Ensure DB connection is using sqlite in-memory for this test
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        // Create attendances table minimal schema on sqlite connection
        Schema::connection('sqlite')->create('attendances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->date('date');
            $table->string('status')->nullable();
            $table->text('remarks')->nullable();
            $table->string('period')->nullable();
            $table->string('subject')->nullable();
            $table->string('class')->nullable();
            $table->string('session')->nullable();
            $table->unsignedBigInteger('marked_by')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('device_info')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlite')->dropIfExists('attendances');
        parent::tearDown();
    }

    public function test_attendance_stats_uses_credit_policy_for_late_and_half_day()
    {
        $date = '2026-06-01';
        $class = '10A';

        Attendance::create(['date' => $date, 'class' => $class, 'status' => 'present']);
        Attendance::create(['date' => $date, 'class' => $class, 'status' => 'late']);
        Attendance::create(['date' => $date, 'class' => $class, 'status' => 'half_day']);
        Attendance::create(['date' => $date, 'class' => $class, 'status' => 'absent']);
        Attendance::create(['date' => $date, 'class' => $class, 'status' => 'leave']);

        $stats = Attendance::getAttendanceStats($date, $class);

        // credit = 1 + 1 + 0.5 = 2.5 ; total = 5 ; rate = 50.0
        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(1, $stats['present']);
        $this->assertEquals(1, $stats['absent']);
        $this->assertEquals(1, $stats['late']);
        $this->assertEquals(1, $stats['half_day']);
        $this->assertEquals(1, $stats['leave']);
        $this->assertEquals(2.5, $stats['attendance_credit']);
        $this->assertEquals(50.0, $stats['attendance_rate']);
        $this->assertEquals(50.0, $stats['percentage']);
    }

    public function test_attendance_stats_keeps_present_absent_late_counts()
    {
        $date = '2026-06-02';
        $class = '10A';

        Attendance::create(['date' => $date, 'class' => $class, 'status' => 'present']);
        Attendance::create(['date' => $date, 'class' => $class, 'status' => 'absent']);
        Attendance::create(['date' => $date, 'class' => $class, 'status' => 'late']);

        $stats = Attendance::getAttendanceStats($date, $class);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['present']);
        $this->assertEquals(1, $stats['absent']);
        $this->assertEquals(1, $stats['late']);
    }

    public function test_attendance_stats_adds_half_day_credit_and_percentage()
    {
        $date = '2026-06-03';
        $class = '10A';

        Attendance::create(['date' => $date, 'class' => $class, 'status' => 'present']);
        Attendance::create(['date' => $date, 'class' => $class, 'status' => 'half_day']);

        $stats = Attendance::getAttendanceStats($date, $class);

        // credit = 1 + 0.5 = 1.5 ; total = 2 ; rate = 75.0
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1.5, $stats['attendance_credit']);
        $this->assertEquals(75.0, $stats['attendance_rate']);
    }

    public function test_attendance_stats_keeps_leave_as_legacy_zero_credit()
    {
        $date = '2026-06-04';
        $class = '10A';

        Attendance::create(['date' => $date, 'class' => $class, 'status' => 'leave']);
        Attendance::create(['date' => $date, 'class' => $class, 'status' => 'present']);

        $stats = Attendance::getAttendanceStats($date, $class);

        // credit = 1 ; total = 2 ; rate = 50
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1.0, $stats['attendance_credit']);
        $this->assertEquals(50.0, $stats['attendance_rate']);
    }

    public function test_student_monthly_report_uses_credit_policy_for_percentage()
    {
        $studentId = 1;
        Attendance::create(['student_id' => $studentId, 'date' => '2026-05-01', 'status' => 'present']);
        Attendance::create(['student_id' => $studentId, 'date' => '2026-05-02', 'status' => 'late']);
        Attendance::create(['student_id' => $studentId, 'date' => '2026-05-03', 'status' => 'half_day']);

        $report = Attendance::getStudentMonthlyReport($studentId, '05', '2026');

        // credit = 1 + 1 + 0.5 = 2.5 ; total = 3 ; rate = 83.33
        $this->assertEquals(3, $report['summary']['total_days']);
        $this->assertEquals(2.5, $report['summary']['attendance_credit']);
        $this->assertEquals(round((2.5/3)*100,2), $report['summary']['attendance_rate']);
    }

    public function test_student_monthly_report_counts_half_day_and_late_correctly()
    {
        $studentId = 2;
        Attendance::create(['student_id' => $studentId, 'date' => '2026-05-05', 'status' => 'half_day']);
        Attendance::create(['student_id' => $studentId, 'date' => '2026-05-06', 'status' => 'late']);

        $report = Attendance::getStudentMonthlyReport($studentId, '05', '2026');

        $this->assertEquals(2, $report['summary']['total_days']);
        $this->assertEquals(1, $report['summary']['late']);
        $this->assertEquals(1, $report['summary']['half_day']);
    }

    public function test_student_monthly_report_keeps_day_wise_records()
    {
        $studentId = 3;
        Attendance::create(['student_id' => $studentId, 'date' => '2026-05-10', 'status' => 'present', 'remarks' => 'On time']);

        $report = Attendance::getStudentMonthlyReport($studentId, '05', '2026');

        $this->assertCount(1, $report['details']);
        $this->assertEquals('2026-05-10', $report['details'][0]['date']);
        $this->assertEquals('present', $report['details'][0]['status']);
        $this->assertEquals('On time', $report['details'][0]['remarks']);
    }

    public function test_student_monthly_report_handles_empty_month()
    {
        $studentId = 4;

        $report = Attendance::getStudentMonthlyReport($studentId, '04', '2026');

        $this->assertEquals(0, $report['summary']['total_days']);
        $this->assertEquals(0.0, $report['summary']['attendance_credit']);
        $this->assertEquals(0.0, $report['summary']['attendance_rate']);
    }
}
